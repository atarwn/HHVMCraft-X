<?php

namespace HHVMCraft\Core\Entities;

use Evenement\EventEmitter;
use HHVMCraft\API\Coordinates3D;
use HHVMCraft\Core\Networking\Packets\DestroyEntityPacket;
use HHVMCraft\Core\Networking\Packets\EntityMetadataPacket;
use HHVMCraft\Core\Networking\Packets\EntityTeleportPacket;
use HHVMCraft\Core\Networking\Packets\EntityVelocityPacket;
use HHVMCraft\Core\Physics\PhysicsEngine;
use HHVMCraft\Core\World\Chunk;

class EntityManager {
    public $Server;
    public $World;
    public $PhysicsEngine;
    public $Event;
    
    public $pendingDespawns = [];
    public $entities = [];
    public $nextEntityId = 1;

    public function __construct($Server, $World) {
        $this->Server = $Server;
        $this->World = $World;
        $this->PhysicsEngine = new PhysicsEngine($World, $Server->BlockRepository);
        $this->Event = new EventEmitter();

        $this->Event->on("PropertyChanged", function ($sender, $propertyName) {
            $this->handlePropertyChanged($sender, $propertyName);
        });
    }

    public function handlePropertyChanged($entity, $propertyName) {
        if (get_class($entity) == "PlayerEntity") {
            $this->handlePlayerPropertyChanged($propertyName, $entity);
        }

        switch ($propertyName) {
            case "Position":
            case "Yaw":
            case "Pitch":
                $this->propagateEntityPositionUpdates($entity);
                break;
            case "Metadata":
                $this->propagateEntityMetadataUpdates($entity);
                break;
        }
    }

    public function handlePlayerPropertyChanged($propertyName, $playerEntity) {
        switch ($propertyName) {
            case "Position":
                if (($playerEntity->Position->x >> 4) != ($playerEntity->OldPosition->x >> 4) ||
                    ($playerEntity->Position->z >> 4) != ($playerEntity->OldPosition->z >> 4)) {
                    $this->Server->loop->nextTick(function() use ($playerEntity) {
                        $this->updateClientEntities($playerEntity->Client);
                    });
                }
                break;
        }
    }

    public function updateClientEntities($client) {
        $entity = $client->PlayerEntity;

        // Remove entities from the client that have moved out of range of the client.
        foreach ($client->knownEntities as $key => $knownEntity) {
            if ($knownEntity->Position->distanceTo($entity->Position) > $client->chunkRadius * Chunk::Depth) {
                $client->enqueuePacket(new DestroyEntityPacket($knownEntity->entityId));
                unset($client->knownEntities[$key]);

                if (get_class($knownEntity) == "PlayerEntity") {
                    $c = $knownEntity->Client;

                    if (in_array($entity, $c->knownEntities)) {
                        unset($c->knownEntities[array_search($entity, $c->knownEntities)]);
                        $c->enqueuePacket(new DestroyEntityPacket($entity->entityId));
                    }
                }
            }
        }

        // Reindex client entities array after we have removed some elements.
        $client->knownEntities = array_values($client->knownEntities);

        // Now get entities that the client should know about
        $entitiesToSpawn = $this->getEntitiesInRange($entity, $client->chunkRadius);
        foreach ($entitiesToSpawn as $e) {
            if ($e != $entity && !in_array($e, $client->knownEntities)) {
                $this->sendEntityToClient($client, $e);

                // If it's a player, make sure that client knows about this entity.
                if (get_class($e) == "PlayerEntity") {
                    $c = $e->Client;

                    if (!in_array($entity, $c->knownEntities)) {
                        $this->sendEntityToClient($c, $entity);
                    }
                }
            }
        }
    }

    public function sendEntityToClient($client, $entity) {
        array_push($client->knownEntities, $entity);

        $client->enqueuePacket($entity->spawnPacket());

        if (get_class($entity) == "PhysicsEntity") {
            $client->enqueuePacket(new EntityVelocityPacket(
                $entity->entityId,
                (int)($entity->Velocity->x * 320),
                (int)($entity->Velocity->y * 320),
                (int)($entity->Velocity->z * 320)));
        }
    }

    public function propagateEntityPositionUpdates($sender) {
        foreach ($this->Server->Clients as $client) {
            if ($client->PlayerEntity == $sender) {
                continue;
            }

            if (in_array($sender, $client->knownEntities)) {
                $client->enqueuePacket(new EntityTeleportPacket(
                    $sender->entityId,
                    $sender->Position->x,
                    $sender->Position->y,
                    $sender->Position->z,
                    (int)((($sender->Yaw % 360) / 360) * 256),
                    (int)((($sender->Pitch % 360) / 360) * 256)));
            }
        }
    }

    public function propagateEntityMetadataUpdates($sender) {
        if (!$sender->sendMetaDataToClients) {
            return;
        }

        foreach ($this->Server->Clients as $client) {
            if ($client->PlayerEntity == $sender) {
                continue;
            }

            if (in_array($sender, $client->knownEntities)) {
                $client->enqueuePacket(new EntityMetadataPacket($sender->entityId, $sender->metadata()));
            }
        }
    }

    public function despawnEntity($Entity) {
        array_push($this->pendingDespawns, $Entity);
        $Entity->despawned = true;
    }

    public function update() {
        // $this->PhysicsEngine->update();

        foreach ($this->entities as $e) {
			if (isset($e->despawned) and $e->despawned == false) {
				$e->update($this);
			}
		}

        $this->flushDespawns();
    }

    public function flushDespawns() {
        while (count($this->pendingDespawns) != 0) {
            $entity = array_shift($this->pendingDespawns);

            if (get_class($entity) == "PhysicsEntity") {
                $this->PhysicsEngine->removeEntity($entity);
            }

            foreach ($this->Server->Clients as $client) {
                if (in_array($entity, $client->knownEntities) && !$client->Disconnected) {
                    $client->enqueuePacket(new DestroyEntityPacket($entity->entityId));
                    unset($client->knownEntities[array_search($entity, $client->knownEntities)]);
                }
            }

            unset($this->entities[array_search($entity, $this->entities)]);
        }
    }

    public function addPlayerEntity($client) {
        $PlayerEntity = new PlayerEntity($client, $this->Event);
        array_push($this->entities, $PlayerEntity);
        return $PlayerEntity;
    }

    public function checkForBlockingEntities($Coordinates3D) {
        $roundedCoordinates = Coordinates3D::rounded($Coordinates3D);
        $result = false;

        // TODO: Ensure we check for the entity height for the 3D bounding box.
        foreach ($this->entities as $entity) {
            $entityCoordinates = Coordinates3D::rounded($entity->Position);

            if ($roundedCoordinates->equalsCoordinates($entityCoordinates->x, $entityCoordinates->y, $entityCoordinates->z) ||
                $roundedCoordinates->equalsCoordinates($entityCoordinates->x, $entityCoordinates->y + 1, $entityCoordinates->z)) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    private function getEntitiesInRange($entity, $chunkRadius) {
        $entitiesInRange = [];

        foreach ($this->entities as $e) {
            if ($e->Position->distanceTo($entity->Position) <= $chunkRadius * Chunk::Depth) {
                $entitiesInRange[] = $e;
            }
        }

        return $entitiesInRange;
    }
}

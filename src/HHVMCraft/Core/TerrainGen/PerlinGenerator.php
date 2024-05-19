<?php
/**
 * PerlinGenerator is part of HHVMCraft - a Minecraft server implemented in PHP
 * - Generates chunks using Perlin noise for more realistic terrain.
 * - Adds water and sand generation.
 *
 * @copyright Andrew Vy 2015
 * @license MIT <https://github.com/andrewvy/HHVMCraft/blob/master/LICENSE.md>
 */
namespace HHVMCraft\Core\TerrainGen;

use HHVMCraft\API\Coordinates3D;
use HHVMCraft\Core\World\Chunk;

class PerlinGenerator {
    const LevelType = "NORMAL";
    public $spawnpoint;
    public $layers = [];
    private $waterLevel = 7; // Water level, any height below this will be water

    public function __construct() {
        $this->spawnpoint = new Coordinates3D(0, 10, 0);
    }

    private function perlinNoise($x, $z, $scale, $octaves = 1, $persistence = 0.5) {
        $total = 0;
        $frequency = $scale;
        $amplitude = 1;
        $maxValue = 0;  // Used for normalizing result to 0.0 - 1.0

        for ($i = 0; $i < $octaves; $i++) {
            $total += $this->smoothNoise($x * $frequency, $z * $frequency) * $amplitude;
            
            $maxValue += $amplitude;
            
            $amplitude *= $persistence;
            $frequency *= 2;
        }

        return $total / $maxValue;
    }

    private function smoothNoise($x, $z) {
        return (sin($x) + cos($z)) / 2;
    }

    public function generateChunk($Coordinates2DPos) {
        $newC = new Chunk($Coordinates2DPos);

        $scale = 0.1; // Scale of the Perlin noise
        $octaves = 4; // Number of octaves
        $persistence = 0.5; // Persistence of the noise

        for ($x = 0; $x < 16; $x++) {
            for ($z = 0; $z < 16; $z++) {
                $worldX = $Coordinates2DPos->x * 16 + $x;
                $worldZ = $Coordinates2DPos->z * 16 + $z;
                $height = (int)(8 + 8 * $this->perlinNoise($worldX, $worldZ, $scale, $octaves, $persistence)); // Height varies from 1 to 15

                for ($y = 0; $y <= $height; $y++) {
                    if ($y == $height) {
                        if ($height <= $this->waterLevel) {
                            $newC->setBlockID(new Coordinates3D($x, $y, $z), 0x08); // Water block
                        } else {
                            $newC->setBlockID(new Coordinates3D($x, $y, $z), 0x02); // Grass block
                        }
                    } else if ($y < $height && $y > 0) {
                        $newC->setBlockID(new Coordinates3D($x, $y, $z), 0x03); // Dirt block
                    }
                }

                // Fill air above water up to the water level with water blocks
                if ($height < $this->waterLevel) {
                    for ($y = $height + 1; $y <= $this->waterLevel; $y++) {
                        $newC->setBlockID(new Coordinates3D($x, $y, $z), 0x08); // Water block
                    }
                }
            }
        }

        // Add sand near water
        for ($x = 0; $x < 16; $x++) {
            for ($z = 0; $z < 16; $z++) {
                $worldX = $Coordinates2DPos->x * 16 + $x;
                $worldZ = $Coordinates2DPos->z * 16 + $z;
                $height = (int)(8 + 8 * $this->perlinNoise($worldX, $worldZ, $scale, $octaves, $persistence)); // Recompute height

                if ($height > $this->waterLevel) {
                    // Check surrounding blocks for water to place sand
                    if (($height - 1) <= $this->waterLevel ||
                        $this->isAdjacentToWater($newC, $x, $height, $z)) {
                        $newC->setBlockID(new Coordinates3D($x, $height, $z), 0x0C); // Sand block
                    }
                }
            }
        }

        // Add bedrock layer
        for ($x = 0; $x < 16; $x++) {
            for ($z = 0; $z < 16; $z++) {
                $newC->setBlockID(new Coordinates3D($x, 0, $z), 0x07); // Bedrock block
            }
        }

        return $newC;
    }

    private function isAdjacentToWater($chunk, $x, $y, $z) {
        $directions = [
            [-1, 0], [1, 0], [0, -1], [0, 1]
        ];
        foreach ($directions as $dir) {
            $dx = $x + $dir[0];
            $dz = $z + $dir[1];
            if ($dx >= 0 && $dx < 16 && $dz >= 0 && $dz < 16) {
                $blockID = $chunk->getBlockID(new Coordinates3D($dx, $y - 1, $dz));
                if ($blockID == 0x08) {
                    return true;
                }
            }
        }
        return false;
    }
}

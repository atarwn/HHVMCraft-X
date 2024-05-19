![logo](https://github.com/atarwn/HHVMCraft/raw/master/docs/hhvmcraft.png)

Works on PHP 7.0.0 RC6 and HHVM 3.10.1!

A Minecraft Beta 1.7.3 Server implemented in PHP, based off of 
[Truecraft's implementation](https://github.com/SirCmpwn/TrueCraft). Powered by [ReactPHP](http://reactphp.org/) (with some parts patched).

![demo](https://github.com/atarwn/HHVMCraft/raw/master/docs/demo.png)

The goal of this project is not to be a fully-functional server,
but rather a proof-of-concept for PHP and HHVM.

### Installation?

You need composer!

`composer install`

and

`php server.php` or `hhvm server.php`!

Make sure you're using the b1.7.3 client! Check the `Allow use of Old Beta Minecraft Versions`.

![](https://cloud.githubusercontent.com/assets/2051361/11055769/2b601e68-872f-11e5-81f3-da8c1a9e83ff.png)

### Why?

This is a little coding exercise/project, it's not intended to be a serious 
application. :)

As you can tell, this is not the most performant implementation. It's being
developed in a way that's fast to code, and readable.

I am also not a PHP programmer, or an advanced-level programmer, nor do I know much about socket programming! ;)

So if you have any comments or if you have any crazy fun/interesting ideas for me to consider, please feel free to create an Github issue!

## Current Progress

View [Things to make functional demo](https://github.com/atarwn/HHVMCraft/issues/1)!

## Libraries used

[Evenement](https://github.com/igorw/evenement) - Event Dispatching Library  
[ReactPHP](https://github.com/reactphp/react) - Low-level async event-driven library for PHP.  
[PHPUnit](https://phpunit.de) - PHP Testing Framework  
[Monolog](https://github.com/Seldaek/monolog) - Logging Framework  

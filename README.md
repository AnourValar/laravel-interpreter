# Laravel Interpreter

## Installation

```bash
composer require anourvalar/laravel-interpreter --dev
```


## Usage

First step: Create translate schema (config) for target locale

```bash
php artisan interpreter:schema de
```


Second step: Generate translation file for specified schema

```bash
php artisan interpreter:export de
```


Third step: Generate locale's files using filled translation file

```bash
php artisan interpreter:import de
```

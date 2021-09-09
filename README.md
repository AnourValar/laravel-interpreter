# Laravel Interpreter

This package allows you:
* collect all untranslated phrases from your project to a single file for a translator.
* save all translated phrases from a single file to the project's structure.


## Installation

```bash
composer require anourvalar/laravel-interpreter --dev
```


## Usage

Step #1: Create schema (config) for a target locale

```bash
php artisan interpreter:schema ru
```


Step #2: Fill in the config

```json
{
    "source_locale": "en", // reference (fallback) locale
    "target_locale": "ru",

    "adapter": "AnourValar\\LaravelInterpreter\\Adapters\\JsonAdapter",
    "filename": "te_i18.json",

    "include_json": true, // include phrases from lang/en.json

    "lang_files": {
        "exclude": [],

        "include": [
            "/admin/" // include all files inside folder lang/en/admin/*
		],

        "exclude_keys": []
    },

    "view_files": {
        "exclude": [],

        "include": [
            "/admin/" // include all files (parse phrases) inside folder views/admin/*
		]
    },

    "exclude_phrases": []
}
```


Step #3: Generate a single file using schema

```bash
php artisan interpreter:export ru
```


Step #4: Save filled single file to the project's structure

```bash
php artisan interpreter:import ru
```

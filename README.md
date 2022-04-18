# Laravel Interpreter
* Export all untranslated phrases from your project to a single file for a translator.
* Import all translated phrases from a single file to your project's structure.
* Find & wrap text phrases with missed @lang() directive in your blade templates.


## Installation

```bash
composer require anourvalar/laravel-interpreter --dev
```


## Usage: basic flow

**Step #1: Create schema (config) for a target locale**

```bash
php artisan interpreter:schema ru
```


**Step #2: Fill in the config**

```json
{
  "source_locale": "en", // reference (source) locale
  "target_locale": "ru",

  "adapter": "AnourValar\\LaravelInterpreter\\Adapters\\JsonAdapter",
  "filename": "ru_i18.json",

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


**Step #3: Export untranslated phrases to a single file for a translator**

```bash
php artisan interpreter:export ru
```

*This command also display all unwrapped (with missing @lang) phrases.*


**Step #4: Import the completed single file to the project's structure**

```bash
php artisan interpreter:import ru
```


## Usage: wrap

```bash
php artisan interpreter:wrap resources/views/test.blade.php
```

**Original template**

```html
<div>Привет, Мир!</div>
```


**Modified template**

```html
<div>@lang('Привет, Мир!')</div>
```

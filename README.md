# aero-package
Package for Laravel. Chart aero sounding data

# Installation  
You may install package via Composer

    composer require f2re/aero
    
# Routes
Package has some routes:
    
      Route::get('/aero', 'F2re\Aero\Controllers\AeroController@index');
      Route::get('/aero/{stantion}', 'F2re\Aero\Controllers\AeroController@bystantion');

# How to use
All available stations accesible on url: **/aero** in JSON format:
```javascript
{
  "stantions": {
    "10035": {
      "id": "10035",
      "name": "Шлесвиг",
      "name_en": "Schleswig",
      "country": "Германия",
      "lat": "54,52",
      "lon": "9,54"
    },
    "10113": {
      "id": "10113",
      "name": "Норденой",
      "name_en": "Norderney",
      "country": "Германия",
      "lat": "53,71",
      "lon": "7,15"
    },
    ...
}
```

To get aerologic diagramm you can do request to url **/aero/{stantion.id}**, where `{stantion.id}` is number of station. The response in JSON format will be:
```javascript
{
  "path": "/png/10035-2019-12-08-12.png"
}
```


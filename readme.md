# MediaLibraryConnector

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![The Whole Fruit Manifesto](https://img.shields.io/badge/writing%20standard-the%20whole%20fruit-brightgreen)](https://github.com/the-whole-fruit/manifesto)

##### Upload files using Spatie Media Library with Backpack fields.

This package provides the ability to store files in projects that use the [Backpack for Laravel](https://backpackforlaravel.com/) administration panel with [Spatie Media Library](https://github.com/spatie/laravel-media-library). 

More exactly, it provides some helper classes that will handle the file uploads and retrieve them back to use on the UI.


## Screenshots

> **// TODO: add a screenshot and delete these lines;** 
> to add a screenshot to a github markdown file, the easiest way is to
> open an issue, upload the screenshot there with drag&drop, then close the issue;
> you now have that image hosted on Github's servers; so you can then right-click 
> the image to copy its URL, and use that URL wherever you want (for example... here)

![Backpack Toggle Field Addon](https://via.placeholder.com/600x250?text=screenshot+needed)


## Installation

#### 1. Install the package
``` bash
composer require backpack/media-library-uploads
```
#### 2. Publish the config file
```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="config"
```

#### 3. Publish&Run the migrations
```bash
# optionaly publish the migrations:
# php artisan vendor:publish --provider="Backpack\MediaLibraryUploads" --tag="migrations"

php artisan migrate
```

Prepare the Model/Models where you plan to use the media funcionality. Basically add the `InteractsWithMedia` trait to your model and implement the `HasMedia` interface like explained on [Media Library Documentation](https://spatie.be/docs/laravel-medialibrary/v10/basic-usage/preparing-your-model).

## Usage

You should setup the regular field in your CRUD controller, and then tell Backpack that the upload should be done through Spatie Media Library by adding `->withMedia()` to your field definition:

```php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia();

```

For repeatable fields you should add `->withMedia()` to the repeatable field (the parent), and then in the subfields, you should mark each field that should be handled by Media Library with `'withMedia' => true`.

```php
CRUD::field('gallery')
        ->label('Image Gallery')
        ->type('repeatable')
        ->subfields([
            [
                'name' => 'main_image',
                'label' => 'Main Image',
                'type' => 'image',
                'withMedia' => true,
            ],
        ])
        ->withMedia(); 
```

This will register functions in your model events  `saving` and `retrieved`. 
The `saving` event will handle the file upload and the `retrieved` event will retrieve the file from the media library and set it to the field for display.

## Configuration

You can pass a closure to `->withMedia(function($media) { ... })` or `'withMedia' => function($media) { ... }` to customize the options, or totally overwrite the behavior.

```php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia(function($media, $field) {
            return $media->toMediaCollection('my_collection', 'my_disk');
        });
```
**NOTE:** If you manually set the `collection`, `disk` or `media name`, you should also set the `disk` and `collection` and `mediaName` attributes as a second closure in the `->withMedia()` method.

```php
->withMedia(
    saveCallback: function($media, $field) {
        return $media->toMediaCollection('my_collection', 'my_disk');
    },
    getCallback: function($field) {
        return $field->disk('my_disk')->collection('my_collection');
    }
);
```
## Overwriting

Extend the class and use your custom classes.

## Change log

Changes are documented here on Github. Please see the [Releases tab](https://github.com/backpack/media-library-connector/releases).

## Testing

``` bash
composer test
```

## Contributing

Please see [contributing.md](contributing.md) for a todolist and howtos.

## Security

If you discover any security related issues, please email hello@backpackforlaravel.com instead of using the issue tracker.

## Credits

- [Tabacitu][link-author]
- [All Contributors][link-contributors]

## License

This project was released under MIT, so you can install it on top of any Backpack & Laravel project. Please see the [license file](license.md) for more information. 

However, please note that you do need Backpack installed, so you need to also abide by its [YUMMY License](https://github.com/Laravel-Backpack/CRUD/blob/master/LICENSE.md). That means in production you'll need a Backpack license code. You can get a free one for non-commercial use (or a paid one for commercial use) on [backpackforlaravel.com](https://backpackforlaravel.com).


[ico-version]: https://img.shields.io/packagist/v/backpack/media-library-connector.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/backpack/media-library-connector.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/backpack/media-library-connector
[link-downloads]: https://packagist.org/packages/backpack/media-library-connector
[link-author]: https://github.com/backpack
[link-contributors]: ../../contributors

# Spatie Media Library Uploaders for Backpack CRUD fields & columns

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![The Whole Fruit Manifesto](https://img.shields.io/badge/writing%20standard-the%20whole%20fruit-brightgreen)](https://github.com/the-whole-fruit/manifesto)

If you project uses both [Spatie Media Library](https://github.com/spatie/laravel-medialibrary) and [Backpack for Laravel](https://backpackforlaravel.com/), this add-on provides the ability for:
- Backpack fields to easily store uploaded files as media (by using Spatie Media Library);
- Backpack columns to easily retrieve uploaded files as media; 

More exactly, it provides the `->withMedia()` helper, that will handle the file upload and retrieval using [Backpack Uploaders](https://backpackforlaravel.com/docs/{{version}}/crud-uploaders). You'll love how simple it makes uploads!

## Requirements

**Install and use `spatie/laravel-medialibrary` v10**. If you haven't already, please make sure you've installed `spatie/laravel-medialibrary` and followed all installation steps in [their docs](https://spatie.be/docs/laravel-medialibrary/v10/installation-setup):

``` bash
# require the package
composer require "spatie/laravel-medialibrary:^10.0.0"

# prepare the database
# NOTE: Spatie migration does not come with a `down()` method by default, add one now if you need it
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="migrations"

# run the migration
php artisan migrate

# make sure you have your storage symbolic links created for the default laravel `public` disk
php artisan storage:link

# (optionally) publish the config file
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="config"

```

Then prepare your Models to use `spatie/laravel-medialibrary`, by adding the `InteractsWithMedia` trait to your model and implement the `HasMedia` interface like explained on [Media Library Documentation](https://spatie.be/docs/laravel-medialibrary/v10/basic-usage/preparing-your-model).

## Installation

DURING BETA, please add this to your `composer.json`'s `repositories` section, to pull the package directly from Github:

```json
"backpack/medialibrary-uploaders": {
            "type": "vcs",
            "url": "https://github.com/Laravel-Backpack/medialibrary-uploaders.git"
        }
```

Just require this package using Composer, that's it:

``` bash
composer require backpack/medialibrary-uploaders
```

## Usage

On any field where you upload a file (eg. `upload`, `upload_multiple`, `image` or `dropzone`), add `withMedia()` to your field definition, in order to tell Backpack to store those uploaded files using Spatie's Laravel MediaLibrary. For example:

```php
CRUD::field('avatar')->type('image')->withMedia();

// you can also do that on columns:
CRUD::column('avatar')->type('image')->withMedia();

// and on subfields:
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
        ]); 
```

## Advanced Use

### Overriding the defaults

Backpack sets up some handy defaults for you when handling the media. But it also provides you a way to customize the bits you need from Spatie Media Library. You can pass a configuration array to `->withMedia([])` or `'withMedia' => []` to override the defaults Backpack has set.

```php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia([
            'collection' => 'my_collection', // default: the spatie config default
            'disk' => 'my_disk', // default: the spatie config default
            'mediaName' => 'custom_media_name' // default: the field name
        ]);
```

### Customizing the saving process (adding thumbnails, responsive images  etc)

Inside the same configuration array mentioned above, you can use the `whenSaving` closure to customize the saving process. This closure will be called in THE MIDDLE of configuring the media collection. So AFTER calling the initializer function, but BEFORE calling toMediaCollection(). Do what you want to the $spatieMedia object, using Spatie's documented methods, then `return` it back to Backpack to call the termination method. Sounds good?

```php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia([
            'whenSaving' => function($spatieMedia, $backpackMediaObject) {
                return $spatieMedia->usingFileName('main_image.jpg')
                                    ->withResponsiveImages();
            }
        ]);
```

**NOTE:** Some methods will be called automatically by Backpack; You shoudn't call them inside the closure used for configuration: `toMediaCollection()`, `setName()`, `usingName()`, `setOrder()`, `toMediaCollectionFromRemote()` and `toMediaLibrary()`. They will throw an error if you manually try to call them in the closure. 

### Defining media collection in the model

You can also have the collection configured in your model as explained in [Spatie Documentation](https://spatie.be/docs/laravel-medialibrary/v10/working-with-media-collections/defining-media-collections), in that case, you just need to pass the `collection` configuration key. But you are still able to configure all the other options including the `whenSaving` callback.

```php
// In your Model.php

public function registerMediaCollections(): void
{
    $this
        ->addMediaCollection('product_images')
        ->useDisk('products');
}

// And in YourCrudController.php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia([
            'collection' => 'product_images', // will pick the collection definition from your model
        ]);
```

### Working with Conversions

Sometimes you will want to create conversions for your images, like thumbnails etc. In case you want to display some conversions instead of the original image on the field you should define `displayConversions => 'conversion_name'` or `displayConversions => ['higher_priority_conversion', 'second_priority_conversion']`. 

In the end, if none of the conversions are ready yet (maybe they are still queued), we will display the original file as a fallback. 

```php
// In your Model.php

public function registerMediaConversions(): void
{
    $this->addMediaConversion('thumb')
                ->width(368)
                ->height(232)
                ->keepOriginalImageFormat()
                ->nonQueued();
}

// And in YourCrudController.php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia([
            'displayConversions' => 'thumb'
        ]);
        
// you can also configure aditional manipulations in the `whenSaving` callback
->withMedia([
    'displayConversions' => 'thumb',
    'whenSaving' => function($media) {
        return $media->withManipulations([
            'thumb' => ['orientation' => 90]
        ]);
    }
]);

```

### Custom properties

You can normally assign custom properties to your media with `->withCustomProperties([])` as stated in spatie documentation, but please be advise that `name`, `repeatableContainerName` and `repeatableRow` are **reserved keywords** and Backpack values will **always** overwrite yours.

```php
'whenSaving' => function($media) {
        return $media->withCustomProperties([
            'my_property' => 'value',
            'name' => 'i_cant_use_this_key'
        ]);
    }

// the saved custom properties will be: 
//  - [my_property => value, name => main_image, repeatableRow => null, repeatableContainerName => null]`
```



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

- [Pedro Martins](https://github.com/pxpm/) - author & architect
- [Cristian Tabacitu](https://github.com/tabacitu) - reviewer
- [Backpack for Laravel](https://github.com/laravel-backpack) - sponsor
- [All Contributors][link-contributors]

## License

This project was released under MIT, so you can install it on top of any Backpack & Laravel project. Please see the [license file](license.md) for more information. 


[ico-version]: https://img.shields.io/packagist/v/backpack/media-library-connector.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/backpack/media-library-connector.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/backpack/medialibrary-uploaders
[link-downloads]: https://packagist.org/packages/backpack/medialibrary-uploaders
[link-author]: https://github.com/backpack
[link-contributors]: ../../contributors

# Laravel Dusk Visual Assert

This package adds assertions to compare screenshots taken during [Laravel Dusk](https://laravel.com/docs/10.x/dusk#taking-a-screenshot) tests using the Imagick extension.

## Installation

You can install the package via composer:

```bash
composer require --dev rossjcooper/laravel-dusk-visual-assert
```

## Configuration

Publish the config file to control default settings:

```bash
php artisan vendor:publish --tag=visual-assert-config
```

## Usage

The Dusk Browser class now has access to some new methods:

### assertScreenshot()

This method will take a screenshot of the current page and compare it to a reference image (generated the first time the test is run).

If the images are different, the test will fail and save the image diff so you can inspect the differences.

```php
$browser->assertScreenshot(string $name, float|null $threshold = null, int|null $metric = null, int|null $width = null, int|null $height = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/')
        ->assertScreenshot('home');
});
```

### assertElementScreenshot()

Take a screenshot of a specific element and compare it to a reference image. Perfect for testing individual components without worrying about the rest of the page.

```php
$browser->assertElementScreenshot(string $selector, string $name, float|null $threshold = null, int|null $metric = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/login')
        // Test that the login form hasn't changed
        ->assertElementScreenshot('#login-form', 'login-form')
        
        // Test header with custom threshold
        ->assertElementScreenshot('header', 'header', 0.02);
});
```

### assertResponsiveScreenshots()

This method is similar to the `assertScreenshot` as above but it screenshots the page at different screen sizes.

```php
$browser->assertResponsiveScreenshots(string $name, float|null $threshold = null, int|null $metric = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/')
        ->assertResponsiveScreenshots('home');
});
```

### assertResponsiveElementScreenshots()

Test an element at different responsive breakpoints.

```php
$browser->assertResponsiveElementScreenshots(string $selector, string $name, float|null $threshold = null, int|null $metric = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/products')
        // Test product grid at different screen sizes
        ->assertResponsiveElementScreenshots('.product-grid', 'product-grid');
});
```

### assertElementsScreenshots()

Test multiple elements at once by providing an array of selectors and names.

```php
$browser->assertElementsScreenshots(array $elements, float|null $threshold = null, int|null $metric = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/dashboard')
        ->assertElementsScreenshots([
            '#sidebar' => 'dashboard-sidebar',
            '.user-profile' => 'user-profile-card',
            '#main-content' => 'main-content-area',
        ]);
});
```

### assertScreenshotWithoutFixed()

Take a screenshot with fixed elements (like sticky headers, chat widgets, cookie banners) automatically hidden.

```php
$browser->assertScreenshotWithoutFixed(string $name, array|null $selectorsToHide = null, float|null $threshold = null, int|null $metric = null, int|null $width = null, int|null $height = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/')
        // Uses default selectors from config
        ->assertScreenshotWithoutFixed('homepage-clean')
        
        // Hide specific elements
        ->assertScreenshotWithoutFixed('homepage-custom', [
            '.chat-widget',
            '#cookie-banner',
            '.sticky-nav'
        ]);
});
```

### assertElementScreenshotWithoutFixed()

Take an element screenshot with fixed elements hidden.

```php
$browser->assertElementScreenshotWithoutFixed(string $selector, string $name, array|null $selectorsToHide = null, float|null $threshold = null, int|null $metric = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/products')
        ->assertElementScreenshotWithoutFixed(
            '.product-grid',
            'products-no-overlay',
            ['.promo-popup', '.flash-sale-banner']
        );
});
```

### withoutFixedElements()

Temporarily hide fixed elements for multiple operations.

```php
$browser->withoutFixedElements(callable $callback, array|null $selectors = null)
```

Example:

```php
$this->browse(function (Browser $browser) {
    $browser->visit('/dashboard')
        ->withoutFixedElements(function ($browser) {
            return $browser
                ->assertScreenshot('dashboard-clean')
                ->assertElementScreenshot('#charts', 'charts-clean');
        }, ['.fixed']);
});
```

## Updating reference images

If you want to update the reference images simply delete them from the `tests/Browser/screenshots/references` directory and re-run your tests to generate new ones.

I would recommend committing the reference images to your repository so you can track changes to them over time.

## Caveats

When comparing images, the package will expect the screenshots to be the same width and height as the reference images.

```
Error: Screenshots are not the same size, ensure the screenshots are taken using the same Dusk environment.
Failed asserting that false is true.
```

If the Dusk environment has changed (headless-mode, window size, etc) then the comparison screenshots could be different sizes and the assertion will fail.

You can change the `skip_if_different_window_size` config option to overcome this if you need to use a different Dusk environment temporarily.
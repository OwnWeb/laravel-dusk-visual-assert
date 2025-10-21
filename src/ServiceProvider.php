<?php
namespace Rossjcooper\LaravelDuskVisualAssert;

use Imagick;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/visual-assert.php' => config_path('visual-assert.php'),
        ], 'visual-assert-config');

        Browser::macro('assertScreenshot', function (string $name, float|null $threshold = null, int|null $metric = null, int $width = null, int $height = null) {
            /** @var Browser $this */

            $threshold = $threshold ?? config('visual-assert.default_threshold');
            $metric = $metric ?? config('visual-assert.default_metric');
            $width = $width ?? config('visual-assert.screenshot_width');
            $height = $height ?? config('visual-assert.screenshot_height');

            $filePath = sprintf('%s/references/%s.png', rtrim(Browser::$storeScreenshotsAt, '/'), $name);

            $diffName = sprintf('%s-diff', $name);
            $diffFilePath = sprintf('%s/diffs/%s.png', rtrim(Browser::$storeScreenshotsAt, '/'), $diffName);

            $directoryPath = dirname($filePath);

            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0777, true);
            }

            if (!file_exists($filePath)) {
                $this->resize($width, $height);
                $this->driver->takeScreenshot($filePath);
                Assert::assertTrue(true, 'Reference screenshot stored successfully.');

                return $this;
            }


            $this->resize($width, $height);
            $this->driver->takeScreenshot($diffFilePath);

            $originalImage = new Imagick($filePath);
            $diffImage = new Imagick($diffFilePath);

            if (
                $originalImage->getImageWidth() !== $diffImage->getImageWidth()
                || $originalImage->getImageHeight() !== $diffImage->getImageHeight()
            ) {
                if (config('visual-assert.skip_if_different_window_size', false)) {
                    return $this;
                }
                Assert::assertTrue(false, sprintf("Screenshots are not the same size (original: %dx%d, current: %dx%d). The browser window size may have changed between screenshots."));

                return $this;
            }

            $result = $originalImage->compareImages($diffImage, $metric);

            if ($result[1] > $threshold) {
                $result[0]->setImageFormat("png");
                $result[0]->writeImage($diffFilePath);
            } else {
                unlink($diffFilePath);
            }

            Assert::assertLessThanOrEqual($threshold, $result[1], sprintf('Screenshots are not the same. Difference can be viewed at: %s', $diffFilePath));

            return $this;
        });

        // Element screenshot assertion macro
        Browser::macro('assertElementScreenshot', function (string $selector, string $name, float|null $threshold = null, int|null $metric = null) {
            /** @var Browser $this */
            $threshold = $threshold ?? config('visual-assert.default_threshold');
            $metric = $metric ?? config('visual-assert.default_metric');

            // Ensure the name indicates it's an element screenshot
            if (!str_contains($name, 'element')) {
                $name = 'element-' . $name;
            }

            $filePath = sprintf('%s/references/%s.png', rtrim(Browser::$storeScreenshotsAt, '/'), $name);
            $diffName = sprintf('%s-diff', $name);
            $diffFilePath = sprintf('%s/diffs/%s.png', rtrim(Browser::$storeScreenshotsAt, '/'), $diffName);

            // Create directories if they don't exist
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0777, true);
            }

            // If no reference exists, create it
            if (!file_exists($filePath)) {
                // Use Dusk's built-in screenshotElement method
                $this->screenshotElement($selector, $name);

                // Move the screenshot to the references folder
                $defaultPath = sprintf('%s/%s.png', rtrim(Browser::$storeScreenshotsAt, '/'), $name);
                if (file_exists($defaultPath)) {
                    rename($defaultPath, $filePath);
                }

                Assert::assertTrue(true, 'Reference element screenshot stored successfully.');
                return $this;
            }

            $diffName = sprintf('diffs/%s', $name);

            // Take current element screenshot for comparison
            $this->screenshotElement($selector, $diffName);

            $diffFilePath = sprintf('%s/%s.png', rtrim(Browser::$storeScreenshotsAt, '/'), $diffName);

            // Compare images
            $originalImage = new Imagick($filePath);
            $currentImage = new Imagick($diffFilePath);

            if (
                $originalImage->getImageWidth() !== $currentImage->getImageWidth()
                || $originalImage->getImageHeight() !== $currentImage->getImageHeight()
            ) {
                if (config('visual-assert.skip_if_different_element_size', false)) {
                    return $this;
                }

                Assert::assertTrue(false, sprintf("Element screenshots are not the same size (original: %dx%d, current: %dx%d). The element may have changed dimensions. Difference can be viewed at: %s",
                    $originalImage->getImageWidth(),
                    $originalImage->getImageHeight(),
                    $currentImage->getImageWidth(),
                    $currentImage->getImageHeight(),
                    $diffFilePath
                ));
                return $this;
            }

            $result = $originalImage->compareImages($currentImage, $metric);

            // If there's a difference above threshold, save the diff
            if ($result[1] > $threshold) {
                // Also create a visual diff image
                $visualDiffPath = sprintf('%s/diffs/%s-visual.png', rtrim(Browser::$storeScreenshotsAt, '/'), $name);
                $result[0]->setImageFormat("png");
                $result[0]->writeImage($visualDiffPath);

                Assert::assertLessThanOrEqual($threshold, $result[1], sprintf('Element screenshots are not the same. Difference can be viewed at: %s and visual diff at: %s', $diffFilePath, $visualDiffPath));
            } else {
                // Clean up temp file
                Assert::assertLessThanOrEqual($threshold, $result[1], 'Element screenshots match within threshold.');
            }

            return $this;
        });

        Browser::macro('assertResponsiveScreenshots', function (string $name, float|null $threshold = null, int|null $metric = null) {
            /** @var Browser $this */
            $threshold = $threshold ?? config('visual-assert.default_threshold');
            $metric = $metric ?? config('visual-assert.default_metric');

            if (substr($name, -1) !== '/') {
                $name .= '-';
            }

            foreach (Browser::$responsiveScreenSizes as $device => $size) {
                $this->assertScreenshot("$name$device", $threshold, $metric, $size['width'], $size['height']);
            }

            return $this;
        });

        // Responsive element screenshots macro
        Browser::macro('assertResponsiveElementScreenshots', function (string $selector, string $name, float|null $threshold = null, int|null $metric = null) {
            /** @var Browser $this */
            $threshold = $threshold ?? config('visual-assert.default_threshold');
            $metric = $metric ?? config('visual-assert.default_metric');

            if (substr($name, -1) !== '/') {
                $name .= '-';
            }

            foreach (Browser::$responsiveScreenSizes as $device => $size) {
                // Resize window for responsive testing
                $this->resize($size['width'], $size['height']);

                // Take element screenshot at this size
                $this->assertElementScreenshot($selector, "$name$device", $threshold, $metric);
            }

            return $this;
        });

        // Helper macro to assert multiple elements at once
        Browser::macro('assertElementsScreenshots', function (array $elements, float|null $threshold = null, int|null $metric = null) {
            /** @var Browser $this */
            $threshold = $threshold ?? config('visual-assert.default_threshold');
            $metric = $metric ?? config('visual-assert.default_metric');

            foreach ($elements as $selector => $name) {
                $this->assertElementScreenshot($selector, $name, $threshold, $metric);
            }

            return $this;
        });

        // Macro to hide fixed/sticky elements before taking screenshots
        Browser::macro('withoutFixedElements', function (callable $callback, array $selectors = null) {
            /** @var Browser $this */

            // Use default selectors if none provided
            $selectorsToHide = $selectors ?? config('visual-assert.fixed_elements_to_hide', []);

            // Build CSS to hide fixed elements
            $cssRules = [];

            // Add custom selectors
            foreach ($selectorsToHide as $selector) {
                $cssRules[] = "{$selector} { display: none !important; }";
            }

            // Inject CSS to hide elements
            $styleId = 'visual-assert-hide-fixed-' . uniqid();
            $css = implode(' ', $cssRules);

            if (!empty($css)) {
                $this->script("
                    var style = document.createElement('style');
                    style.id = '{$styleId}';
                    style.innerHTML = `{$css}`;
                    document.head.appendChild(style);
                ");
            }

            try {
                // Execute the callback (take screenshots, etc.)
                $result = $callback($this);
            } finally {
                // Always restore by removing the injected CSS
                if (!empty($css)) {
                    $this->script("
                        var style = document.getElementById('{$styleId}');
                        if (style) {
                            style.remove();
                        }
                    ");
                }
            }

            return $result ?? $this;
        });

        // Macro for screenshot without fixed elements
        Browser::macro('assertScreenshotWithoutFixed', function (string $name, array $selectorsToHide = null, float|null $threshold = null, int|null $metric = null, int $width = null, int $height = null) {
            /** @var Browser $this */
            return $this->withoutFixedElements(function ($browser) use ($name, $threshold, $metric, $width, $height) {
                return $browser->assertScreenshot($name, $threshold, $metric, $width, $height);
            }, $selectorsToHide);
        });

        // Macro for element screenshot without fixed elements
        Browser::macro('assertElementScreenshotWithoutFixed', function (string $selector, string $name, array $selectorsToHide = null, float|null $threshold = null, int|null $metric = null) {
            /** @var Browser $this */
            return $this->withoutFixedElements(function ($browser) use ($selector, $name, $threshold, $metric) {
                return $browser->assertElementScreenshot($selector, $name, $threshold, $metric);
            }, $selectorsToHide);
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/visual-assert.php', 'visual-assert'
        );
    }
}
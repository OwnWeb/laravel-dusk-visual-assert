<?php

return [

    // Keep the screenshots the same size for better comparison
    'screenshot_width' => 1920,
    'screenshot_height' => 1080,

    // For more info on how images are compared see
    // https://www.php.net/manual/en/imagick.compareimages.php
    'default_threshold' => 0.002,
    'default_metric' => \Imagick::METRIC_MEANSQUAREERROR,

    'skip_if_different_window_size' => false,

    /**
     * Skip assertion if element sizes don't match (for element screenshots)
     * If true, the test will pass without comparison when element dimensions differ
     * If false, the test will fail when element dimensions differ
     */
    'skip_if_different_element_size' => false,

    /**
     * CSS selectors for fixed elements to hide during screenshots
     */
    'fixed_elements_to_hide' => [
        '.fixed',
        '.sticky',
    ],
];

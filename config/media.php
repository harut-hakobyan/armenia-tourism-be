<?php

declare(strict_types=1);

return [
    'image_quality' => (int) env('MEDIA_IMAGE_QUALITY', 82),
    'max_image_dimension' => (int) env('MEDIA_MAX_IMAGE_DIMENSION', 2560),
];

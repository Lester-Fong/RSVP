<?php

/** Small synthetic JPEG: four colored corners, EXIF orientation, and removable private metadata. */
return static function (int $orientation): string {
    $image = imagecreatetruecolor(80, 40);
    imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 40, 0, 79, 19, imagecolorallocate($image, 0, 255, 0));
    imagefilledrectangle($image, 0, 20, 39, 39, imagecolorallocate($image, 0, 0, 255));
    imagefilledrectangle($image, 40, 20, 79, 39, imagecolorallocate($image, 255, 255, 0));
    ob_start();
    imagejpeg($image, null, 100);
    $jpeg = ob_get_clean();
    $metadata = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0".pack('V', 0).'GPS_LOCATION_PRIVATE';

    return substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($metadata) + 2).$metadata.substr($jpeg, 2).'<script>private payload</script>';
};

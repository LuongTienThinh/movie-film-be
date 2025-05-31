<?php
// Thiết lập log lỗi và tăng memory limit
// ini_set('log_errors', 1);
// ini_set('error_log', 'my-error.log');
// ini_set('memory_limit', '512M');

// Ghi log khởi tạo
error_log('Bắt đầu xử lý ảnh. Tham số: src=' . ($_GET['src'] ?? 'không có') . ', w=' . ($_GET['w'] ?? 'không có') . ', h=' . ($_GET['h'] ?? 'không có') . ', zc=' . ($_GET['zc'] ?? 'không có'));

// Kiểm tra và tải thư viện Intervention Image
if (!file_exists('../vendor/autoload.php')) {
    error_log('Lỗi: Không tìm thấy file vendor/autoload.php tại ' . realpath('../vendor/autoload.php'));
    header('HTTP/1.1 500 Internal Server Error');
    exit('Lỗi server: Không tìm thấy autoload.php');
}
require '../vendor/autoload.php';
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

// Kiểm tra xem lớp ImageManager có tồn tại không
if (!class_exists('Intervention\Image\ImageManager')) {
    error_log('Lỗi: Class Intervention\Image\ImageManager không tồn tại. Kiểm tra cài đặt intervention/image.');
    header('HTTP/1.1 500 Internal Server Error');
    exit('Lỗi server: Thư viện Intervention Image không được cài đặt đúng');
}
error_log('Đã tải thư viện Intervention Image version 3.x');

// Kiểm tra PHP extensions
$availableDrivers = [];
if (extension_loaded('gd')) {
    $availableDrivers[] = 'gd';
}
if (extension_loaded('imagick')) {
    $availableDrivers[] = 'imagick';
}
if (empty($availableDrivers)) {
    error_log('Lỗi: Không tìm thấy GD hoặc Imagick extension. Cần cài đặt ít nhất một trong hai.');
    header('HTTP/1.1 500 Internal Server Error');
    exit('Lỗi server: Không tìm thấy GD hoặc Imagick extension');
}
error_log('Các driver khả dụng: ' . implode(', ', $availableDrivers));

// Kiểm tra WebP support
$webpSupport = false;
if (in_array('gd', $availableDrivers)) {
    $gdInfo = gd_info();
    $webpSupport = isset($gdInfo['WebP Support']) && $gdInfo['WebP Support'];
    error_log('GD WebP Support: ' . ($webpSupport ? 'Enabled' : 'Disabled'));
} elseif (in_array('imagick', $availableDrivers)) {
    $webpSupport = in_array('WEBP', Imagick::queryFormats());
    error_log('Imagick WebP Support: ' . ($webpSupport ? 'Enabled' : 'Disabled'));
}
if (!$webpSupport) {
    error_log('Lỗi: Driver ' . (in_array('gd', $availableDrivers) ? 'gd' : 'imagick') . ' không hỗ trợ WebP.');
    header('HTTP/1.1 500 Internal Server Error');
    exit('Lỗi server: Driver không hỗ trợ định dạng WebP');
}

// Khởi tạo ImageManager với driver ưu tiên Imagick nếu có
try {
    $driver = in_array('imagick', $availableDrivers) ? new ImagickDriver() : new GdDriver();
    $manager = new ImageManager($driver);
    error_log('Đã khởi tạo ImageManager với driver ' . (in_array('imagick', $availableDrivers) ? 'imagick' : 'gd'));
} catch (Exception $e) {
    error_log('Lỗi khi khởi tạo ImageManager: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Lỗi server: Không thể khởi tạo ImageManager: ' . $e->getMessage());
}

// Lấy tham số từ URL
$src = isset($_GET['src']) ? $_GET['src'] : '';
$width = isset($_GET['w']) ? (int)$_GET['w'] : 0;
$height = isset($_GET['h']) ? (int)$_GET['h'] : 0;
$zc = isset($_GET['zc']) ? (int)$_GET['zc'] : 0; // 0: không crop, 1: crop giữ tỷ lệ, 2: crop thông minh

// Kiểm tra tham số
if (empty($src) || $width <= 0 || $height <= 0) {
    error_log('Lỗi: Thiếu tham số hoặc không hợp lệ. src=' . $src . ', width=' . $width . ', height=' . $height);
    header('HTTP/1.1 400 Bad Request');
    exit('Thiếu tham số hoặc không hợp lệ');
}
error_log('Tham số hợp lệ: src=' . $src . ', width=' . $width . ', height=' . $height . ', zc=' . $zc);

// Kiểm tra file ảnh tồn tại và có thể truy cập
$tempFile = null;
$originalSrc = $src; // Lưu URL gốc để lấy extension
if (!file_exists($src) && filter_var($src, FILTER_VALIDATE_URL)) {
    // Kiểm tra URL bằng cURL
    $ch = curl_init($src);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        error_log('Lỗi: Không thể truy cập URL ảnh: ' . $src . '. Mã HTTP: ' . $httpCode);
        header('HTTP/1.1 400 Bad Request');
        exit('Không thể truy cập URL ảnh. Mã HTTP: ' . $httpCode);
    }
    // Tải ảnh về file tạm
    $tempFile = tempnam(sys_get_temp_dir(), 'img_');
    $ch = curl_init($src);
    $fp = fopen($tempFile, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if ($httpCode !== 200 || !filesize($tempFile)) {
        unlink($tempFile);
        error_log('Lỗi: Không thể tải ảnh từ URL: ' . $src . '. Mã HTTP: ' . $httpCode);
        header('HTTP/1.1 400 Bad Request');
        exit('Không thể tải ảnh từ URL');
    }
    $src = $tempFile;
    error_log('Đã tải ảnh về file tạm: ' . $src);
} elseif (!file_exists($src)) {
    error_log('Lỗi: File ảnh không tồn tại hoặc URL không hợp lệ: ' . $src);
    header('HTTP/1.1 400 Bad Request');
    exit('File ảnh không tồn tại hoặc URL không hợp lệ');
}
error_log('File ảnh hợp lệ: ' . $src);

// Kiểm tra định dạng ảnh
$allowedFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
// Lấy extension từ URL gốc nếu file tạm không có
$extension = strtolower(pathinfo($tempFile ? $originalSrc : $src, PATHINFO_EXTENSION));
if (empty($extension)) {
    // Kiểm tra MIME type nếu không có extension
    $imageInfo = getimagesize($src);
    if ($imageInfo === false) {
        if ($tempFile) unlink($tempFile);
        error_log('Lỗi: Không thể xác định định dạng ảnh: ' . $src);
        header('HTTP/1.1 400 Bad Request');
        exit('Không thể xác định định dạng ảnh');
    }
    $mime = $imageInfo['mime'];
    $mimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $extension = $mimeToExtension[$mime] ?? '';
    error_log('Đã xác định extension từ MIME type: ' . $extension);
}
if (!in_array($extension, $allowedFormats)) {
    if ($tempFile) unlink($tempFile);
    error_log('Lỗi: Định dạng ảnh không được hỗ trợ: ' . $extension);
    header('HTTP/1.1 400 Bad Request');
    exit('Định dạng ảnh không được hỗ trợ');
}
error_log('Định dạng ảnh được hỗ trợ: ' . $extension);

// Kiểm tra kích thước ảnh gốc
$imageInfo = getimagesize($src);
if ($imageInfo === false) {
    if ($tempFile) unlink($tempFile);
    error_log('Lỗi: Không thể lấy thông tin kích thước ảnh: ' . $src);
    header('HTTP/1.1 400 Bad Request');
    exit('Không thể lấy thông tin ảnh');
}
error_log('Kích thước ảnh gốc: width=' . $imageInfo[0] . ', height=' . $imageInfo[1]);

// Giới hạn kích thước tối đa
$maxWidth = 3000;
$maxHeight = 3000;
$width = min($width, $maxWidth);
$height = min($height, $maxHeight);
error_log('Kích thước sau giới hạn: width=' . $width . ', height=' . $height);

// Tải và xử lý ảnh
try {
    error_log('Bắt đầu tải ảnh: ' . $src);
    $image = $manager->read($src);
    error_log('Đã tải ảnh thành công');

    // Resize ảnh gốc nếu vượt quá giới hạn
    if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
        $image->scale(width: $maxWidth, height: $maxHeight);
        error_log('Đã resize ảnh gốc để phù hợp giới hạn: width=' . $maxWidth . ', height=' . $maxHeight);
    }

    // Xử lý resize và crop dựa trên zc
    error_log('Bắt đầu xử lý resize/crop với zc=' . $zc);
    if ($zc == 1) {
        $image->cover($width, $height);
        error_log('Đã thực hiện cover: width=' . $width . ', height=' . $height);
    } elseif ($zc == 2) {
        $image->scale(width: $width, height: $height)->crop($width, $height);
        error_log('Đã thực hiện scale và crop: width=' . $width . ', height=' . $height);
    } else {
        $image->scale(width: $width, height: $height);
        error_log('Đã thực hiện scale không crop: width=' . $width . ', height=' . $height);
    }

    // Nén ảnh với chất lượng 80
    error_log('Bắt đầu nén ảnh sang WebP, chất lượng=80');
    $image->toWebp(80);
    error_log('Đã nén ảnh thành công');

    // Xuất ảnh
    header('Content-Type: image/webp');
    echo $image->encode();
    error_log('Đã xuất ảnh thành công');

} catch (Exception $e) {
    error_log('Lỗi khi xử lý ảnh: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Lỗi khi xử lý ảnh: ' . $e->getMessage());
} finally {
    if ($tempFile && file_exists($tempFile)) {
        unlink($tempFile);
        error_log('Đã xóa file tạm: ' . $tempFile);
    }
}
?>
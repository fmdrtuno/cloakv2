<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['blacklist_page_title'] ?? 'Nội dung không có sẵn'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php
        // Assuming $settings is available from the main script that includes this file.
        echo get_tracking_script('google', $settings);
        echo get_tracking_script('tiktok', $settings);
        echo get_tracking_script('meta', $settings);
    ?>
    <style>
        body {
            background-color: #f0f2f5;
        }
        .content-card {
            max-width: 800px;
            margin: 2rem auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .content-img {
            width: 100%;
            height: auto;
        }
        .content-body {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content-card">
            <div class="content-body">
                <h1 class="mb-4 text-center"><?php echo htmlspecialchars($settings['blacklist_page_title'] ?? ''); ?></h1>

                <p class="lead"><?php echo nl2br(htmlspecialchars($settings['blacklist_page_content1'] ?? '')); ?></p>
                <?php if (!empty($settings['blacklist_page_image1'])): ?>
<img src="<?php echo htmlspecialchars($settings['blacklist_page_image1']); ?>" class="img-fluid rounded my-3 d-block mx-auto" alt="Hình ảnh 1">
                <?php endif; ?>

                <p><?php echo nl2br(htmlspecialchars($settings['blacklist_page_content2'] ?? '')); ?></p>
                <?php if (!empty($settings['blacklist_page_image2'])): ?>
<img src="<?php echo htmlspecialchars($settings['blacklist_page_image2']); ?>" class="img-fluid rounded my-3 d-block mx-auto" alt="Hình ảnh 2">
                <?php endif; ?>

                <p><?php echo nl2br(htmlspecialchars($settings['blacklist_page_content3'] ?? '')); ?></p>
                <?php if (!empty($settings['blacklist_page_image3'])): ?>
<img src="<?php echo htmlspecialchars($settings['blacklist_page_image3']); ?>" class="img-fluid rounded my-3 d-block mx-auto" alt="Hình ảnh 3">
                <?php endif; ?>

                <?php if (isset($honeypot_enabled) && $honeypot_enabled): ?>
                <div class="text-center mt-4">
                    <a href="legal/privacy.php" class="btn btn-secondary">Xem Chính sách Bảo mật</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <footer style="text-align: center; padding: 20px; font-size: 14px; color: #888;">
            <?php if (!empty($settings['contact_name'])) echo htmlspecialchars($settings['contact_name']) . '<br>'; ?>
            <?php if (!empty($settings['contact_address'])) echo htmlspecialchars($settings['contact_address']) . '<br>'; ?>
            <?php if (!empty($settings['contact_email'])) echo 'Email: ' . htmlspecialchars($settings['contact_email']); ?>
            <?php if (!empty($settings['contact_phone'])) echo ' | Phone: ' . htmlspecialchars($settings['contact_phone']); ?>
        </footer>
    </div>
</body>
</html>

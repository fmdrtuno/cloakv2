# Cloak v2 - Landing Page System

## Tổng quan

Đây là một hệ thống quản lý landing page, được thiết kế để che giấu (cloaking) và chuyển hướng lưu lượng truy cập. Dự án bao gồm một backend mạnh mẽ để quản lý và một frontend linh hoạt để hiển thị nội dung.

## Cấu trúc dự án

Dự án được chia thành hai phần chính: `frontend` và `backend`.

### Backend (`/backend`)

Phần backend được viết bằng PHP, chịu trách nhiệm xử lý logic cốt lõi của hệ thống.

-   **API (`/backend/api.php`)**: Cung cấp các điểm cuối (endpoints) để frontend tương tác.
-   **Bảng quản trị (`/backend/admin/`)**: Giao diện quản trị để cấu hình hệ thống, bao gồm:
    -   Quản lý người dùng, mật khẩu.
    -   Quản lý tên miền, blacklist.
    -   Cấu hình các chế độ (Honeypot, Redirect, Stealth, Verification).
    -   Quản lý các mẫu (templates) landing page.
-   **Cấu hình (`/backend/config/config.php`)**: Tệp cấu hình chính cho backend.
-   **Cache (`/backend/cache/CacheManager.php`)**: Quản lý việc lưu trữ cache, có thể tương tác với Redis.
-   **Uploads (`/backend/uploads/`)**: Lưu trữ các tệp được tải lên.
-   **Thư viện (`/backend/vendor/`)**: Các thư viện phụ thuộc được quản lý bởi Composer.

### Frontend (`/frontend`)

Phần frontend chịu trách nhiệm hiển thị nội dung cho người dùng cuối.

-   **Proxy API (`/frontend/api_proxy.php`)**: Có thể hoạt động như một proxy để giao tiếp với backend API một cách an toàn.
-   **Trang chính (`/frontend/index.php`)**: Tệp chính để xử lý và hiển thị landing page.
-   **Mẫu (`/frontend/templates/`)**: Chứa các mẫu landing page khác nhau. Hệ thống có thể chuyển đổi giữa các mẫu này.
-   **Xác minh (`/frontend/verification.js`)**: Kịch bản JavaScript để xử lý logic xác minh phía client.
-   **Cấu hình (`/frontend/config.ini`)**: Tệp cấu hình cho frontend.

## Quản lý phiên bản

Dự án này sử dụng Git để quản lý phiên bản. Lịch sử thay đổi được lưu lại trong mỗi commit.

-   **Nhánh `main`**: Chứa phiên bản sản phẩm (production) ổn định.
-   **Nhánh `develop`**: Chứa các tính năng mới nhất đang được phát triển.

Mọi thay đổi cần được thực hiện trên một nhánh riêng, sau đó tạo **Pull Request** để hợp nhất vào `develop`, và cuối cùng là vào `main` sau khi được xem xét và phê duyệt.

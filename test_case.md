# BỘ KỊCH BẢN KIỂM THỬ HỆ THỐNG QUẢN LÝ SINH VIÊN (27 TEST CASES)

* **Môi trường thử nghiệm**: `http://localhost:8081/BTL` (hoặc cổng cấu hình XAMPP của bạn)
* **Tài khoản kiểm thử**:
  * Admin: `admin` / Mật khẩu quản trị hệ thống
  * Sinh viên chưa đổi mật khẩu: `B23DCAT001` (MK mặc định: theo ngày sinh trong DB, ví dụ `15102005`)
  * Sinh viên đã đổi mật khẩu: Mật khẩu mới được thiết lập
* **Mục tiêu**: Hướng dẫn chi tiết từng thao tác click/nhập liệu để bạn chạy trực tiếp trên giao diện và ghi nhận kết quả.

---

## BẢNG TỔNG KẾT THEO DÕI KIỂM THỬ

| Nhóm | Số lượng | Đạt (Pass) | Không đạt (Fail) | Ghi chú |
| :--- | :---: | :---: | :---: | :--- |
| **AUTH** (Xác thực & Bảo mật) | 7 | | | Kiểm tra đăng nhập, đổi MK, phân quyền, session |
| **DATA** (Quản lý Danh mục & Ràng buộc) | 5 | | | Kiểm tra CRUD Lớp, Môn, Sinh viên & Khóa ngoại |
| **GRADE** (Quản lý Điểm & Tính toán) | 5 | | | Kiểm tra công thức, validate điểm, AJAX & CSV |
| **REP** (Báo cáo & Xuất bản in) | 4 | | | Kiểm tra bảng xếp hạng GPA, nợ môn, Print A4 |
| **MAIL** (Cảnh báo học vụ & Logs) | 3 | | | Kiểm tra gửi thử SMTP, gửi hàng loạt AJAX & Log DB |
| **STU** (Cổng thông tin Sinh viên) | 3 | | | Kiểm tra Dashboard cá nhân, lọc điểm & sửa hồ sơ |
| **TỔNG CỘNG** | **27** | | | |

---

## CHI TIẾT KỊCH BẢN 27 TEST CASES

### PHẦN 1: AUTH (Xác thực, Phân quyền & Quản lý Phiên)

#### TC-AUTH-01: Đăng nhập với tài khoản sai
* **Mục tiêu**: Hệ thống từ chối đăng nhập và giữ an toàn dữ liệu.
* **Các bước thực hiện**:
  1. Truy cập trang `login.php`.
  2. Nhập Tên đăng nhập: `admin_sai` và Mật khẩu: `12345678`.
  3. Bấm nút **"Đăng nhập"**.
* **Kết quả mong đợi**:
  - Không chuyển trang (vẫn ở `login.php`).
  - Xuất hiện thông báo lỗi: *"Tên đăng nhập hoặc mật khẩu không chính xác!"*.
  - Ô mật khẩu được làm rỗng.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-AUTH-02: Đăng nhập quyền Quản trị viên (Admin)
* **Mục tiêu**: Admin đăng nhập thành công vào trang quản trị với đầy đủ menu.
* **Các bước thực hiện**:
  1. Truy cập `login.php`.
  2. Nhập tài khoản `admin` và mật khẩu đúng.
  3. Bấm **"Đăng nhập"**.
* **Kết quả mong đợi**:
  - Chuyển hướng tới `admin/index.php`.
  - Thanh Sidebar hiển thị đầy đủ: Lớp học, Môn học, Sinh viên, Nhập điểm, Báo cáo, Gửi mail cảnh báo.
  - Header hiển thị lời chào Admin và nút Đăng xuất.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-AUTH-03: Sinh viên chưa đổi mật khẩu lần đầu bị cưỡng chế đổi
* **Mục tiêu**: Sinh viên có cờ `doi_mat_khau = 1` không thể truy cập dashboard khi chưa đổi mật khẩu.
* **Các bước thực hiện**:
  1. Đăng xuất tài khoản admin.
  2. Tại `login.php`, nhập `B23DCAT001` và mật khẩu mặc định (DDMMYYYY).
  3. Quan sát URL và giao diện sau khi bấm đăng nhập.
  4. Cố tình gõ trên thanh địa chỉ URL: `http://localhost:8081/BTL/student/index.php` rồi Enter.
* **Kết quả mong đợi**:
  - Hệ thống tự động chuyển hướng về `change_password.php`.
  - Hiển thị thông báo yêu cầu đổi mật khẩu lần đầu.
  - Cố tình truy cập `student/index.php` vẫn bị đẩy ngược về `change_password.php`.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-AUTH-04: Xác thực lỗi form đổi mật khẩu (Validation)
* **Mục tiêu**: Ngăn chặn đổi mật khẩu nếu xác nhận không khớp hoặc trùng mật khẩu cũ.
* **Các bước thực hiện**:
  1. Tại trang `change_password.php` (tài khoản `B23DCAT001`).
  2. Thử trường hợp 1: Nhập mật khẩu mới giống hệt mật khẩu cũ (ngày sinh). Bấm "Lưu".
  3. Thử trường hợp 2: Mật khẩu mới là `MatKhau@123`, mật khẩu xác nhận là `MatKhau@456`. Bấm "Lưu".
* **Kết quả mong đợi**:
  - Trường hợp 1: Báo lỗi *"Mật khẩu mới không được trùng với mật khẩu mặc định/mật khẩu cũ!"*.
  - Trường hợp 2: Báo lỗi *"Mật khẩu xác nhận không khớp!"*.
  - Mật khẩu trong DB không bị thay đổi.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-AUTH-05: Đổi mật khẩu thành công và mở khóa quyền truy cập
* **Mục tiêu**: Cập nhật mật khẩu băm, gỡ bỏ cờ `doi_mat_khau` và vào được dashboard sinh viên.
* **Các bước thực hiện**:
  1. Tại `change_password.php`, nhập mật khẩu cũ đúng.
  2. Nhập mật khẩu mới hợp lệ: `Ptithcm@2025` và xác nhận chính xác.
  3. Bấm **"Đổi mật khẩu"**.
* **Kết quả mong đợi**:
  - Hệ thống thông báo thành công và chuyển hướng vào `student/index.php`.
  - Trong CSDL, trường `doi_mat_khau` của tài khoản đổi về `0`.
  - Đăng xuất ra và đăng nhập lại bằng `Ptithcm@2025` thành công.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-AUTH-06: Ngăn chặn truy cập trái quyền (Authorization)
* **Mục tiêu**: Sinh viên không được phép truy cập vào các đường dẫn của Admin.
* **Các bước thực hiện**:
  1. Đang đăng nhập tài khoản sinh viên (`B23DCAT001`).
  2. Nhập trực tiếp vào thanh địa chỉ: `http://localhost:8081/BTL/admin/index.php` hoặc `http://localhost:8081/BTL/admin/students.php`.
  3. Nhấn Enter.
* **Kết quả mong đợi**:
  - Bị chặn lại ngay lập tức (không hiển thị bảng điều khiển Admin).
  - Bị chuyển hướng về `student/index.php` hoặc `login.php` kèm thông báo lỗi truy cập bị từ chối.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-AUTH-07: Đăng xuất hủy Session và vô hiệu hóa nút Back
* **Mục tiêu**: Hủy bỏ phiên làm việc, không lưu cache dữ liệu nhạy cảm.
* **Các bước thực hiện**:
  1. Đang ở phiên làm việc của Admin hoặc Sinh viên, bấm nút **"Đăng xuất"**.
  2. Trình duyệt quay về trang `login.php`.
  3. Bấm nút **Back ($\leftarrow$)** của trình duyệt.
* **Kết quả mong đợi**:
  - Không tải lại dữ liệu phiên làm việc cũ.
  - Nếu trang có nạp lại thì tự động bị đá về `login.php` do session đã bị `session_destroy()`.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

### PHẦN 2: DATA (Quản trị Danh mục & Ràng buộc toàn vẹn)

#### TC-DATA-01: Chặn xóa lớp học đang có sinh viên trực thuộc
* **Mục tiêu**: Đảm bảo toàn vẹn dữ liệu, thông báo rõ ràng kèm số lượng sinh viên.
* **Các bước thực hiện**:
  1. Đăng nhập Admin, vào menu **Quản lý Lớp học** (`classes.php`).
  2. Tìm lớp `D23CQAT01` (lớp đang có 3 sinh viên).
  3. Bấm nút **Xóa** ở cột Thao tác và bấm "OK" trên hộp xác nhận.
* **Kết quả mong đợi**:
  - Thao tác xóa bị chặn.
  - Xuất hiện thông báo đỏ: *"Không thể xóa lớp D23CQAT01 vì đang có 3 sinh viên trực thuộc!"* (có nêu cụ thể số lượng sinh viên).
  - Lớp `D23CQAT01` và 3 sinh viên vẫn giữ nguyên trong DB.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-DATA-02: Thêm mới lớp và xóa lớp rỗng thành công (Single-file PRG)
* **Mục tiêu**: Xác nhận quy trình Create & Delete hoạt động trơn tru trong cùng 1 file.
* **Các bước thực hiện**:
  1. Tại `classes.php`, bấm nút **"+ Thêm Lớp mới"**.
  2. Nhập Mã lớp: `TEST01`, Tên lớp: `Lớp thử nghiệm`, Chuyên ngành: `CNTT`, Khóa: `2025`.
  3. Bấm "Lưu Lớp học".
  4. Sau khi bảng xuất hiện `TEST01`, bấm nút **Xóa** của lớp `TEST01` và xác nhận.
  5. Nhấn phím `F5` trên trình duyệt.
* **Kết quả mong đợi**:
  - Thêm thành công (thông báo xanh, lớp xuất hiện trên bảng).
  - Xóa thành công (lớp biến mất khỏi bảng).
  - Nhấn F5 không bị hiện hộp thoại "Confirm form resubmission" (chuẩn PRG).
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-DATA-03: Chặn xóa môn học đã có bản ghi điểm
* **Mục tiêu**: Bảo vệ bảng điểm, thông báo rõ số bản ghi điểm đang liên kết.
* **Các bước thực hiện**:
  1. Vào menu **Quản lý Môn học** (`subjects.php`).
  2. Tìm môn học đã có điểm (ví dụ mã `INT1155` - Tin học cơ sở).
  3. Bấm nút **Xóa** và đồng ý xác nhận.
* **Kết quả mong đợi**:
  - Hệ thống chặn xóa.
  - Hiển thị thông báo: *"Không thể xóa môn INT1155 vì đã có X bản ghi điểm!"* (với X là số lượng bản ghi điểm thực tế trong DB).
  - Môn học không bị xóa.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-DATA-04: Thêm sinh viên mới đồng bộ tài khoản người dùng
* **Mục tiêu**: Thêm hồ sơ sinh viên kèm transaction tự động tạo tài khoản users với mật khẩu băm ngày sinh.
* **Các bước thực hiện**:
  1. Vào menu **Quản lý Sinh viên** (`students.php`), bấm **"+ Thêm Sinh viên mới"**.
  2. Điền form:
     - Mã SV: `B23DCAT099`
     - Họ tên: `Nguyễn Văn Test`
     - Ngày sinh: `20/11/2005`
     - Giới tính: `Nam`
     - Lớp: chọn một lớp có sẵn
     - Email: `b23dcat099@ptit.edu.vn`
     - Số ĐT: `0987654321`
  3. Bấm **"Lưu Sinh viên"**.
* **Kết quả mong đợi**:
  - Thông báo thêm thành công sinh viên `B23DCAT099`.
  - Sinh viên hiển thị trên bảng với nét chữ đen thường.
  - Trong bảng `users` (nếu có), tồn tại tài khoản `B23DCAT099` với mật khẩu được hash từ `20112005` và `doi_mat_khau = 1`.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-DATA-05: Chỉnh sửa thông tin sinh viên ngay trên Modal
* **Mục tiêu**: Cập nhật thông tin sinh viên mà không bị nhảy trang, khóa mã sinh viên chính.
* **Các bước thực hiện**:
  1. Tại `students.php`, tìm sinh viên vừa tạo `B23DCAT099`.
  2. Bấm nút **"Sửa"** (nút vàng).
  3. Modal hiện lên: Kiểm tra xem Mã SV có bị khóa (disabled/readonly) không.
  4. Sửa Họ tên thành: `Nguyễn Văn Test Đã Sửa`, SĐT thành: `0911223344`.
  5. Bấm **"Lưu thay đổi"**.
* **Kết quả mong đợi**:
  - Dữ liệu trên bảng tự động cập nhật tên mới và SĐT mới.
  - Mã sinh viên `B23DCAT099` không bị thay đổi.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

### PHẦN 3: GRADE (Nhập điểm, Công thức tính & Ràng buộc Rollback)

#### TC-GRADE-01: Kiểm tra công thức tính điểm tổng và quy đổi điểm chữ
* **Mục tiêu**: Kiểm tra tính toán nhẩm phía Client theo công thức: $\text{Tổng} = \text{CC}\times 10\% + \text{GK}\times 30\% + \text{CK}\times 60\%$.
* **Các bước thực hiện**:
  1. Vào trang **Nhập điểm theo lớp & môn học** (`grade_entry.php`).
  2. Chọn Lớp: `D23CQAT01`, Môn: bất kỳ (VD: `BAS1201`), Học kỳ: `2025-1`.
  3. Bấm **"Tải danh sách"**.
  4. Tại hàng sinh viên đầu tiên, nhập: Chuyên cần = `9.0`, Giữa kỳ = `8.0`, Cuối kỳ = `7.0`.
* **Kết quả mong đợi**:
  - Cột "Điểm tổng (Dự tính)" tự động nhảy kết quả: $9.0\times 0.1 + 8.0\times 0.3 + 7.0\times 0.6 = \mathbf{7.50}$.
  - Cột "Điểm chữ" tự động hiển thị: $\mathbf{B}$.
  - Cả hai cột đều hiển thị **chữ đen thường**, không có badge xanh đỏ bo tròn.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-GRADE-02: Ràng buộc giá trị điểm ngoài dải (Rollback Transaction)
* **Mục tiêu**: Từ chối lưu và rollback toàn bộ nếu có bất kỳ điểm nào $< 0$ hoặc $> 10$.
* **Các bước thực hiện**:
  1. Vẫn tại bảng điểm của lớp trên:
     - Sinh viên 1: nhập điểm hợp lệ CC = 8, GK = 8, CK = 8.
     - Sinh viên 2: nhập CC = 8, GK = 8, Cuối kỳ = `12.5` (vượt trần 10).
  2. Bấm nút đỏ **"Lưu toàn bộ bảng điểm"**.
* **Kết quả mong đợi**:
  - Không tải lại trang (xử lý ngầm qua AJAX).
  - Bật hộp cảnh báo màu đỏ: Thông báo Rollback toàn bộ và chỉ rõ sinh viên thứ 2 có điểm `12.5` không hợp lệ.
  - Điểm của sinh viên 1 cũng **không** được lưu vào CSDL (tính toàn vẹn transaction).
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-GRADE-03: Ràng buộc nhập ký tự chữ/ký tự đặc biệt vào ô điểm
* **Mục tiêu**: Hệ thống chặn dữ liệu không phải dạng số thực.
* **Các bước thực hiện**:
  1. Tại ô Chuyên cần của sinh viên bất kỳ, nhập chữ: `abc` hoặc `@#$`.
  2. Cố gắng bấm nút **"Lưu toàn bộ bảng điểm"**.
* **Kết quả mong đợi**:
  - HTML5 form validation chặn lại (ô input yêu cầu định dạng số), hoặc nếu gửi qua backend thì backend trả lỗi: *"Điểm không phải là số hợp lệ"*.
  - Bảng dữ liệu không bị ghi đè.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-GRADE-04: Lưu bảng điểm hợp lệ thành công
* **Mục tiêu**: Lưu thành công toàn bộ điểm khi tất cả các ô đều hợp lệ từ 0 đến 10.
* **Các bước thực hiện**:
  1. Nhập điểm đầy đủ, hợp lệ cho tất cả sinh viên trong danh sách (từ 0.0 đến 10.0).
  2. Bấm **"Lưu toàn bộ bảng điểm"**.
* **Kết quả mong đợi**:
  - Hộp thông báo màu xanh hiển thị: *"Lưu bảng điểm thành công!"*.
  - Tải lại trang (F5): Toàn bộ điểm vừa nhập vẫn được hiển thị chính xác.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-GRADE-05: Xuất bảng điểm CSV đạt chuẩn UTF-8 BOM
* **Mục tiêu**: Nút xuất CSV tải về file mở được trên Microsoft Excel không bị lỗi font tiếng Việt.
* **Các bước thực hiện**:
  1. Tại trang nhập điểm đã chọn lớp và môn, nhìn lên góc phải tiêu đề.
  2. Bấm nút màu xanh lá **"Xuất bảng điểm CSV"**.
  3. Mở file CSV vừa tải về bằng **Microsoft Excel**.
* **Kết quả mong đợi**:
  - Trình duyệt tải ngay file `.csv` có dạng tên: `bang_diem_...csv` (không báo lỗi 404).
  - Tên tiếng Việt có dấu (ví dụ: "Nguyễn Văn An", "Toán rời rạc") hiển thị rõ ràng, không bị biến thành ký tự rác (`???` hay `Ã¡`).
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

### PHẦN 4: REP (Báo cáo, Thống kê & Chuẩn in ấn)

#### TC-REP-01: Báo cáo bảng xếp hạng GPA tích lũy
* **Mục tiêu**: Thống kê danh sách sinh viên xếp theo thứ tự GPA giảm dần kèm xếp loại.
* **Các bước thực hiện**:
  1. Vào menu **Báo cáo học tập** (`reports.php`).
  2. Chọn tab/bộ lọc **"Bảng xếp hạng GPA"** hoặc xem danh sách tổng hợp.
* **Kết quả mong đợi**:
  - Sinh viên được sắp xếp từ GPA cao nhất đến thấp nhất.
  - Cột Họ tên và Lớp hiển thị **chữ đen nét thường** (không có badge màu xám/xanh bao quanh).
  - GPA làm tròn đúng 2 chữ số thập phân (hệ 4), xếp loại học lực hiển thị chuẩn xác (Xuất sắc, Giỏi, Khá, TB, Yếu).
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-REP-02: Báo cáo danh sách sinh viên nợ môn (Điểm F)
* **Mục tiêu**: Lọc chính xác các sinh viên có ít nhất một môn tổng kết $< 4.0$.
* **Các bước thực hiện**:
  1. Tại trang Báo cáo, chọn tab **"Sinh viên nợ môn / Cảnh báo"**.
* **Kết quả mong đợi**:
  - Chỉ hiển thị những sinh viên có môn bị điểm F (hoặc tổng kết $< 4.0$).
  - Bảng liệt kê chi tiết: Mã SV, Họ tên, Tên môn nợ, Số tín chỉ và Điểm tổng kết của môn đó.
  - Những sinh viên qua tất cả các môn tuyệt đối không xuất hiện ở bảng này.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-REP-03: Bản in Báo cáo tổng hợp khổ A4 Ngang (`report_print.php`)
* **Mục tiêu**: Mở trang in báo cáo danh sách tự động kích hoạt hộp thoại in, layout chuẩn A4 ngang.
* **Các bước thực hiện**:
  1. Tại trang Báo cáo, bấm nút **"In báo cáo"**.
  2. Quan sát cửa sổ mới được mở ra.
* **Kết quả mong đợi**:
  - Trình duyệt tự động mở Print Preview (`window.print()`).
  - Hướng giấy mặc định hoặc bố cục tối ưu cho khổ A4 Ngang (Landscape).
  - Ẩn hoàn toàn Sidebar và Navbar. Có tiêu đề Học viện và phần ký tên bên dưới.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-REP-04: Bản in Phiếu kết quả học tập cá nhân khổ A4 Dọc (`student_print.php`)
* **Mục tiêu**: In phiếu kết quả học tập của 1 sinh viên có chữ ký, bố cục chuẩn A4 dọc.
* **Các bước thực hiện**:
  1. Vào danh sách sinh viên hoặc chi tiết sinh viên `B23DCAT001`.
  2. Bấm nút **"In bảng điểm"** (`student_print.php?ma_sv=B23DCAT001`).
* **Kết quả mong đợi**:
  - Mở tab mới và bật ngay hộp thoại in.
  - Phía trên có tiêu đề Quốc hiệu - Tiêu ngữ và tên trường chuẩn mực.
  - Phần thân có đầy đủ thông tin cá nhân (Mã SV, họ tên, lớp, ngành học) và bảng điểm chi tiết các kỳ.
  - Phía dưới có khung chữ ký của **Người lập bảng** và **Trưởng phòng Đào tạo**.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

### PHẦN 5: MAIL (Gửi thư cảnh báo học vụ & Quản lý Logs)

#### TC-MAIL-01: Gửi thử nghiệm 1 email (Test SMTP Connection)
* **Mục tiêu**: Xác thực cấu hình SMTP trong `mail_config.php` có kết nối và gửi thư thật được hay không.
* **Các bước thực hiện**:
  1. Cấu hình thông tin email gửi thật trong `includes/mail_config.php` (hoặc biến môi trường).
  2. Vào trang **Gửi email cảnh báo** (`warning_mail.php`), tìm nút hoặc chức năng **"Gửi mail test"**.
  3. Nhập địa chỉ email nhận của bạn và bấm Gửi.
* **Kết quả mong đợi**:
  - Hệ thống thông báo gửi thành công qua SMTP.
  - Hộp thư đến (hoặc Spam) của email nhận nhận được thư HTML có nội dung kiểm tra từ hệ thống PTIT.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-MAIL-02: Kiểm tra ghi nhật ký gửi email (Email Log)
* **Mục tiêu**: Mỗi email gửi đi (thành công hoặc thất bại) đều phải được lưu vết vào bảng `email_log`.
* **Các bước thực hiện**:
  1. Sau khi chạy TC-MAIL-01, vào menu **Nhật ký gửi mail** (`mail_logs.php`).
* **Kết quả mong đợi**:
  - Xuất hiện một dòng log mới nhất.
  - Ghi nhận đầy đủ: Thời gian gửi, Email người nhận, Tiêu đề thư, Trạng thái (Thành công / Thất bại) và Thông điệp phản hồi từ máy chủ SMTP.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-MAIL-03: Gửi cảnh báo hàng loạt bất đồng bộ (AJAX Progress Bar)
* **Mục tiêu**: Gửi email cho nhiều sinh viên nợ môn mà không làm treo trình duyệt nhờ `ajax_send_warning.php`.
* **Các bước thực hiện**:
  1. Tại `warning_mail.php`, lọc danh sách sinh viên nợ môn.
  2. Bấm nút **"Gửi cảnh báo học vụ cho danh sách đã chọn"**.
  3. Quan sát giao diện trong suốt quá trình gửi.
* **Kết quả mong đợi**:
  - Xuất hiện thanh tiến trình (Progress Bar) chạy từ 0% đến 100%.
  - Từng sinh viên được gửi lần lượt, trạng thái tại mỗi dòng chuyển dần sang màu xanh ("Đã gửi").
  - Trình duyệt không bị đơ hoặc timeout giữa chừng.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

### PHẦN 6: STU (Cổng thông tin & Phân hệ Sinh viên)

#### TC-STU-01: Dashboard sinh viên và Biểu đồ GPA tích lũy
* **Mục tiêu**: Sinh viên xem được hồ sơ cá nhân và biểu đồ theo dõi tiến độ học tập.
* **Các bước thực hiện**:
  1. Đăng nhập bằng tài khoản sinh viên đã đổi mật khẩu (`B23DCAT001`).
  2. Truy cập trang chủ sinh viên `student/index.php`.
* **Kết quả mong đợi**:
  - Không xuất hiện lỗi SQL (đặc biệt là lỗi thiếu cột ngành/chuyên ngành `chuyen_nganh`).
  - Thẻ thông tin cá nhân hiển thị chuẩn: Mã SV, Họ tên, Lớp, Ngành, Khóa học.
  - Biểu đồ GPA (vẽ bằng Chart.js hoặc HTML5 Canvas) hiển thị đường biểu diễn điểm qua các học kỳ.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-STU-02: Sinh viên tra cứu bảng điểm cá nhân & Lọc theo học kỳ
* **Mục tiêu**: Sinh viên xem được chi tiết điểm từng môn và GPA tương ứng của từng học kỳ.
* **Các bước thực hiện**:
  1. Tại phân hệ sinh viên, vào trang **Bảng điểm cá nhân** (`student/transcript.php` hoặc `student/grades.php`).
  2. Chọn lọc theo từng học kỳ (ví dụ: `2024-1`, `2024-2`, `Tất cả`).
* **Kết quả mong đợi**:
  - Bảng chỉ hiển thị điểm của chính sinh viên đó, không thấy điểm sinh viên khác.
  - Hiển thị đầy đủ: CC, GK, CK, Điểm tổng kết và Điểm chữ của từng môn.
  - Tính đúng số tín chỉ tích lũy và GPA của học kỳ được chọn.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail

---

#### TC-STU-03: Sinh viên cập nhật thông tin liên lạc (Hồ sơ cá nhân)
* **Mục tiêu**: Sinh viên chỉ được phép sửa SĐT/Địa chỉ, không được can thiệp vào các trường định danh.
* **Các bước thực hiện**:
  1. Vào mục **Thông tin cá nhân / Hồ sơ** (`student/profile.php`).
  2. Kiểm tra các ô: Mã SV, Họ tên, Ngày sinh, Lớp học $\rightarrow$ Đảm bảo ở trạng thái chỉ đọc (`readonly`).
  3. Thay đổi Số điện thoại mới: `0933445566` và bấm **"Cập nhật"**.
* **Kết quả mong đợi**:
  - Hệ thống báo cập nhật thành công.
  - SĐT mới được lưu vào CSDL; Mã SV, Họ tên và Lớp học không bị thay đổi.
* **Kết quả thực tế**: 
* **Đánh giá**: [ ] Pass  [ ] Fail
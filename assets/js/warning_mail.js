// assets/js/warning_mail.js - Native JS không dùng jQuery
document.addEventListener('DOMContentLoaded', function () {
    const btnSelectAll = document.getElementById('selectAll');
    const chkStudents  = document.querySelectorAll('.student-chk');
    const btnSendBatch = document.getElementById('btnSendBatch');
    const btnSendTest  = document.getElementById('btnSendTest');
    const progressWrap = document.getElementById('progressWrapper');
    const progressBar  = document.getElementById('progressBar');
    const logResult    = document.getElementById('logResult');
    const testResult   = document.getElementById('testResult');

    // 1. Chọn tất cả checkbox
    btnSelectAll?.addEventListener('change', function () {
        chkStudents.forEach(chk => chk.checked = this.checked);
    });

    // 2. Gửi test email
    btnSendTest?.addEventListener('click', async function () {
        const testEmail = document.getElementById('testEmail').value.trim();
        const subject   = document.getElementById('mailSubject').value.trim();
        const message   = document.getElementById('mailMessage').value.trim();
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        if (!testEmail) {
            testResult.innerHTML = '<span class="text-danger">Vui lòng nhập địa chỉ email nhận thử!</span>';
            return;
        }

        btnSendTest.disabled = true;
        btnSendTest.textContent = 'Đang gửi...';
        testResult.innerHTML = '<span class="text-info">Đang kết nối SMTP...</span>';

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('is_test', '1');
        formData.append('test_email', testEmail);
        formData.append('tieu_de', subject);
        formData.append('loi_nhan', message);

        try {
            const res = await fetch('ajax_send_warning.php', { method: 'POST', body: formData });
            const data = await res.json();
            btnSendTest.disabled = false;
            btnSendTest.textContent = 'Gửi thử';

            if (data.status === 'success') {
                testResult.innerHTML = '<span class="text-success fw-bold">✓ Gửi thử thành công!</span>';
            } else {
                testResult.innerHTML = `<span class="text-danger fw-bold">✗ Lỗi: ${data.message}</span>`;
            }
        } catch (e) {
            btnSendTest.disabled = false;
            btnSendTest.textContent = 'Gửi thử';
            testResult.innerHTML = '<span class="text-danger">Lỗi kết nối máy chủ!</span>';
        }
    });

    // 3. Gửi cảnh báo hàng loạt (Sequential async)
    btnSendBatch?.addEventListener('click', async function () {
        const selected = Array.from(chkStudents).filter(c => c.checked).map(c => c.value);
        if (selected.length === 0) {
            alert('Vui lòng tích chọn ít nhất 1 sinh viên trong danh sách!');
            return;
        }

        if (!confirm(`Bạn có chắc muốn gửi email cảnh báo tới ${selected.length} sinh viên đã chọn?`)) {
            return;
        }

        const subject   = document.getElementById('mailSubject').value.trim();
        const message   = document.getElementById('mailMessage').value.trim();
        const semInput  = document.querySelector('input[name="semester"]');
        const hocKy     = semInput ? semInput.value.trim() : '2025-1';
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        btnSendBatch.disabled = true;
        progressWrap.classList.remove('d-none');
        logResult.innerHTML = '';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';

        let successCount = 0;

        for (let i = 0; i < selected.length; i++) {
            const msv = selected[i];
            const row = document.getElementById('row-' + msv);
            const statusCell = row ? row.querySelector('.status-cell') : null;

            if (statusCell) {
                statusCell.innerHTML = '<span class="badge bg-warning text-dark">Đang gửi...</span>';
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('ma_sv', msv);
            formData.append('hoc_ky', hocKy);
            formData.append('tieu_de', subject);
            formData.append('loi_nhan', message);

            try {
                const res = await fetch('ajax_send_warning.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    successCount++;
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge bg-success">Thành công</span>';
                    }
                    logResult.innerHTML += `<div class="text-success">[OK] ${msv}: ${data.message}</div>`;
                } else {
                    if (statusCell) {
                        statusCell.innerHTML = `<span class="badge bg-danger" title="${data.message}">Thất bại</span>`;
                    }
                    logResult.innerHTML += `<div class="text-danger">[LỖI] ${msv}: ${data.message}</div>`;
                }
            } catch (err) {
                if (statusCell) {
                    statusCell.innerHTML = '<span class="badge bg-danger">Mất kết nối</span>';
                }
                logResult.innerHTML += `<div class="text-danger">[LỖI] ${msv}: Lỗi mạng</div>`;
            }

            const pct = Math.round(((i + 1) / selected.length) * 100);
            progressBar.style.width = pct + '%';
            progressBar.textContent = pct + '%';
            logResult.scrollTop = logResult.scrollHeight;
        }

        btnSendBatch.disabled = false;
        alert(`Hoàn thành đợt gửi! Thành công: ${successCount}/${selected.length}`);
    });
});
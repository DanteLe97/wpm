document.addEventListener("DOMContentLoaded", function () {
    // Xử lý button download-pdf-1 - chuyển hướng đến trang PDF
    const btn1 = document.getElementById("download-pdf-1");
    if (btn1) {
        btn1.addEventListener("click", function () {
            const originalText = btn1.textContent;
            btn1.textContent = 'Creating PDF...';
            btn1.style.pointerEvents = 'none';
            btn1.style.opacity = '0.7';

            // Dùng iframe ẩn để tải PDF
            const siteUrl = typeof macPdfAjax !== 'undefined' ? macPdfAjax.siteUrl : window.location.origin;
            const currentPostId = typeof macPdfAjax !== 'undefined' ? macPdfAjax.currentPostId : '';
            const cacheBuster = Date.now();
            const pdfUrl = siteUrl + '/mac-pdf-default' + (currentPostId ? '?post_id=' + currentPostId : '') + (currentPostId ? '&' : '?') + '_=' + cacheBuster;

            // Tạo/ghi đè một iframe ẩn duy nhất
            let iframe = document.getElementById('mac-pdf-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'mac-pdf-iframe';
                iframe.style.position = 'fixed';
                iframe.style.left = '-99999px';
                iframe.style.top = '0';
                iframe.style.width = '1px';
                iframe.style.height = '1px';
                iframe.style.visibility = 'hidden';
                iframe.style.opacity = '0';
                document.body.appendChild(iframe);
            }

            const cleanup = function() {
                btn1.textContent = originalText;
                btn1.style.pointerEvents = 'auto';
                btn1.style.opacity = '1';
                // Giữ iframe để lần sau tái sử dụng, tránh tạo/xóa liên tục
            };

            iframe.onload = function() {
                // Trang endpoint sẽ tự trigger download sau DOMContentLoaded
                // Chờ thêm để đảm bảo tải xong hoặc người dùng save xong
                setTimeout(cleanup, 12000);
            };
            iframe.onerror = function() {
                cleanup();
                alert('Không thể tải trang PDF ẩn.');
            };

            // Gán src để bắt đầu quy trình tải
            iframe.src = pdfUrl;
        });
    }

    // Xử lý button download-pdf gốc
    const btn = document.getElementById("download-pdf");
    if (btn) {
        let rawTitle = document.title;                     // hoặc lấy từ nơi khác
        let decodedTitle = decodeHtmlEntities(rawTitle);  // giải mã HTML entities
        let slug = slugifyKeepCase(decodedTitle);          // tạo slug giữ in hoa
        btn.addEventListener("click", function () {
            // Thêm class vào body khi bắt đầu xuất PDF
            document.body.classList.add('mac-pdf-exporting');
            
			let scale = 2;
			if (document.documentElement.offsetWidth < 767) {
				scale = 1;
			}
            const opt = {
                margin: [10, 10],
                filename: slug+'.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: scale,
                    useCORS: true,
                    scrollX: 0,
                    scrollY: 0,
                    windowWidth: document.body.scrollWidth,
                    windowHeight: document.body.scrollHeight
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'letter',
                    orientation: 'portrait'
                },
                pagebreak: {
                    mode: ['avoid-all', 'css', 'legacy'],
                    avoid: ['img', '.no-break'],
                    after: '#mac-qr'
                }
            };

            // Tạo bản sao của body
            var clonedBody = document.body.cloneNode(true);

            // Reset CSS để không bị lệch/crop
            clonedBody.style.margin = "0";
            clonedBody.style.padding = "0";
            clonedBody.style.width = "100%";
            clonedBody.style.maxWidth = "100%";
            clonedBody.style.boxSizing = "border-box";

            // Ẩn phần không cần in
            clonedBody.querySelectorAll('script, style, link, meta, noscript, .no-print, #jet-theme-core-header, #jet-theme-core-footer, header, footer, #wpadminbar')
                .forEach(el => el.style.display = 'none');

            // Tìm phần tử QR và căn chỉnh
            ['#mac-qr', '#mac-module-qr'].forEach(selector => {
                const qrElement = clonedBody.querySelector(selector);
                if (qrElement) {
                    qrElement.style.pageBreakBefore = 'always';
                    qrElement.style.breakBefore = 'always';
                    qrElement.style.height = '279.4mm';
                    qrElement.style.display = 'flex';
                    qrElement.style.justifyContent = 'center';
                    qrElement.style.alignItems = 'center';
                    qrElement.style.flexDirection = 'column';
                    qrElement.style.textAlign = 'center';
                }
            });

            // Tạo PDF
            html2pdf().set(opt).from(clonedBody).toPdf().get('pdf').then((pdf) => {
                // Lấy số trang
                const totalPages = pdf.internal.getNumberOfPages();
                // Xóa trang cuối nếu có nhiều hơn 1 trang
                if (totalPages > 1) {
                    pdf.deletePage(totalPages);
                }
                // Lưu PDF
                pdf.save(slug + '.pdf');
                // Xóa class khi đã tải xong file PDF
                document.body.classList.remove('mac-pdf-exporting');
            }).catch(err => {
                console.error('Lỗi khi xuất PDF:', err);
                // Xóa class nếu có lỗi
                document.body.classList.remove('mac-pdf-exporting');
            })
        });
    }
    
    function slugifyKeepCase(text) {
        return text
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')       // Bỏ dấu tiếng Việt
          .replace(/[^a-zA-Z0-9\s-]/g, '')       // Loại bỏ ký tự đặc biệt (giữ chữ in hoa)
          .trim()
          .replace(/\s+/g, '-')                  // khoảng trắng -> dấu -
          .replace(/-+/g, '-');                  // gộp nhiều dấu -
    }
    function decodeHtmlEntities(html) {
        const txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    }
});

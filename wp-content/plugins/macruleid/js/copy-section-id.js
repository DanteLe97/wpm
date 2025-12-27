document.addEventListener('DOMContentLoaded', function () {
    // Tạo container và buttons
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'custom-button-container';
    
    // Button 1: Copy Section ID
    const sectionButton = document.createElement('button');
    const sectionHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
        stroke-linecap="round" stroke-linejoin="round" 
        style="vertical-align: middle; margin-right: 4px;">
        <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
        <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
    </svg> Copy ID`;
    
    sectionButton.innerHTML = sectionHTML;
    sectionButton.className = 'custom-button';
    
    // Button 2: Copy Page-Template Format
    const pageTemplateButton = document.createElement('button');
    const pageTemplateHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
        stroke-linecap="round" stroke-linejoin="round" 
        style="vertical-align: middle; margin-right: 4px;">
        <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
        <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
    </svg> Copy`;
    
    pageTemplateButton.innerHTML = pageTemplateHTML;
    pageTemplateButton.className = 'custom-button';
    
    // Thêm buttons vào container
    buttonContainer.appendChild(sectionButton);
    buttonContainer.appendChild(pageTemplateButton);
    document.body.appendChild(buttonContainer);

    let currentTarget = null;

    // Tìm div level 1 chứa element
    function findLevel1Container(element) {
        const wpPageElement = document.querySelector('[data-elementor-type="wp-page"]');
        if (!wpPageElement) return null;
        
        let container = element;
        while (container && container.parentElement !== wpPageElement) {
            container = container.parentElement;
        }
        
        return container && container !== wpPageElement ? container : null;
    }

    // Tìm element có data-id gần nhất
    function findElementWithDataId(element) {
        const wpPageElement = document.querySelector('[data-elementor-type="wp-page"]');
        if (!wpPageElement) return null;
        
        let current = element;
        while (current && current !== wpPageElement) {
            if (current.hasAttribute('data-id')) {
                return current;
            }
            current = current.parentElement;
        }
        return null;
    }

    // Lấy elementor page ID
    function getElementorPageId() {
        const pageElement = document.querySelector('[data-elementor-type="wp-page"][data-elementor-id]');
        return pageElement ? pageElement.getAttribute('data-elementor-id') : '';
    }

    // Tìm class sectionID- và lấy số
    function findSectionId(element) {
        const classes = Array.from(element.classList);
        const sectionIdClass = classes.find(cls => cls.startsWith('sectionID-'));
        
        if (!sectionIdClass) return null;
        
        // Lấy số sau sectionID-
        const sectionId = sectionIdClass.replace('sectionID-', '');
        return sectionId;
    }

    // Tạo text để copy cho section ID
    function createSectionCopyText(element) {
        const sectionId = findSectionId(element);
        return sectionId || '';
    }

    // Tạo text để copy cho page-template
    function createPageTemplateCopyText(element) {
        const elementorPageId = getElementorPageId();
        const dataId = element.getAttribute('data-id');
        
        if (!elementorPageId || !dataId) return '';
        
        return `page:${elementorPageId}-tem:${dataId}`;
    }

    // Hiển thị button
    function showButton(container) {
        if (currentTarget === container) return;
        
        // Xóa button cũ
        const existingContainer = document.querySelector('.custom-button-container');
        if (existingContainer) existingContainer.remove();
        
        // Thêm button mới
        container.style.position = 'relative';
        container.style.boxShadow = '#fdb892 0px 1px 3px 0px, #fdb892 0px 0px 0px 1px';
        
        buttonContainer.style.position = 'absolute';
        buttonContainer.style.top = '10px';
        buttonContainer.style.right = '10px';
        buttonContainer.style.zIndex = '9999';
        buttonContainer.style.display = 'flex';
        buttonContainer.style.gap = '5px';
        
        container.appendChild(buttonContainer);
        currentTarget = container;
    }

    // Copy text to clipboard
    function copyToClipboard(text, button, originalHTML) {
        navigator.clipboard.writeText(text).then(() => {
            const originalText = button.innerHTML;
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                stroke-linecap="round" stroke-linejoin="round" 
                style="vertical-align: middle; margin-right: 4px;">
                <path d="M20 6 9 17l-5-5"/>
            </svg> Copied!`;
            
            setTimeout(() => {
                button.innerHTML = originalText;
            }, 2000);
        }).catch(err => {
            console.error('Could not copy text: ', err);
        });
    }

    // Event listeners
    document.body.addEventListener('mouseover', function (e) {
        const targetElement = findElementWithDataId(e.target);
        if (!targetElement) return;
        
        const level1Container = findLevel1Container(targetElement);
        if (!level1Container) return;
        
        const sectionCopyText = createSectionCopyText(level1Container);
        const pageTemplateCopyText = createPageTemplateCopyText(level1Container);
        
        if (!sectionCopyText && !pageTemplateCopyText) return;
        
        // Set onclick cho section button và ẩn/hiện
        if (sectionCopyText) {
            sectionButton.style.display = 'block';
            sectionButton.onclick = () => copyToClipboard(sectionCopyText, sectionButton, sectionHTML);
        } else {
            sectionButton.style.display = 'none';
        }
        
        // Set onclick cho page-template button
        if (pageTemplateCopyText) {
            pageTemplateButton.onclick = () => copyToClipboard(pageTemplateCopyText, pageTemplateButton, pageTemplateHTML);
        }
        
        showButton(level1Container);
    });

    document.body.addEventListener('mouseout', function (e) {
        const related = e.relatedTarget;
        if (currentTarget && !buttonContainer.contains(related) && 
            (!related || !currentTarget.contains(related))) {
            buttonContainer.style.display = 'none';
            currentTarget.style.boxShadow = '';
            currentTarget = null;
        }
    });
}); 
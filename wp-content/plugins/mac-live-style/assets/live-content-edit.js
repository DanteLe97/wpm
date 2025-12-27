// Realtime update JSON object khi sửa text
function setByPath(obj, path, value) {
  // path dạng [0][elements][1][settings][title]
  let parts = path.match(/\[(.*?)\]/g).map(s => s.replace(/\[|\]/g, ''));
  let ref = obj;
  for (let i = 0; i < parts.length - 1; i++) {
    ref = ref[isNaN(parts[i]) ? parts[i] : parseInt(parts[i])];
  }
  ref[parts[parts.length - 1]] = value;
}

window.macCurrentHighlightEl = null;
window.macCurrentHighlightType = null;
window.macCurrentHighlightScroll = null;

function highlightField(field, updateHtml = false) {
  const form = document.getElementById('edit-content-fields-form');
  if (!form) return;
  const text = field.value.trim();
  const widgetId = field.getAttribute('data-widget-id');
  const fieldId = field.getAttribute('data-mac-field-data-id');
  const fieldLabel = field.previousElementSibling ? field.previousElementSibling.textContent.toLowerCase() : '';
  if (!text) return;

  // Lấy base-id từ field (nếu có)
  const baseId = field.getAttribute('data-mac-base-id') || field.getAttribute('data-base-id');

  // Nếu updateHtml (input), chỉ update nội dung element đã lưu, không tìm lại
  if (updateHtml && window.macCurrentHighlightEl) {
    if (window.macCurrentHighlightType === 'editor') {
      window.macCurrentHighlightEl.innerText = text;
    } else if (window.macCurrentHighlightType === 'text') {
      if (window.macCurrentHighlightEl.nodeType === Node.TEXT_NODE) {
        window.macCurrentHighlightEl.nodeValue = text;
      } else {
        window.macCurrentHighlightEl.innerText = text;
      }
    } else if (window.macCurrentHighlightType === 'block') {
      window.macCurrentHighlightEl.innerText = text;
    }
    if (window.macCurrentHighlightScroll && typeof window.macCurrentHighlightScroll.scrollIntoView === 'function') {
      window.macCurrentHighlightScroll.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
    return;
  }

  // Xóa border cũ
  document.querySelectorAll('.mac-highlight-text').forEach(el => {
    el.classList.remove('mac-highlight-text');
    el.style.border = '';
    el.style.background = '';
    el.style.padding = '';
  });
  window.macCurrentHighlightEl = null;
  window.macCurrentHighlightType = null;
  window.macCurrentHighlightScroll = null;

  // 1) Đường đi nhanh theo ID: nếu đã có element mang ID này thì highlight ngay
  if (fieldId) {
    let mapped = document.querySelector('[data-mac-html-data-id="' + fieldId + '"]') || document.querySelector('[mac-html-data-id="' + fieldId + '"]');
    if (mapped) {
      mapped.classList.add('mac-highlight-text');
      mapped.style.border = '2px solid red';
      mapped.style.padding = '2px';
      mapped.scrollIntoView({behavior: 'smooth', block: 'center'});
      window.macCurrentHighlightEl = mapped;
      window.macCurrentHighlightType = 'block';
      window.macCurrentHighlightScroll = mapped;
      return;
    }
  }

  // Ưu tiên tìm theo base-id (cho slick slides)
  if (baseId) {
    // Tìm slide có data-mac-field-base-id = baseId (cho carousel)
    let originalSlide = document.querySelector('.slick-slide:not(.slick-cloned)[data-mac-field-base-id="' + baseId + '"]');
    
    // Nếu không tìm thấy, tìm theo data-mac-base-id (cho slick slides thông thường)
    if (!originalSlide) {
      originalSlide = document.querySelector('.slick-slide:not(.slick-cloned)[data-mac-base-id="' + baseId + '"]');
    }
    
    if (originalSlide) {
      const widgetEl = originalSlide;
      console.log('Found slide by base-id:', baseId, originalSlide);
      let foundEl = null;
      // Nếu là editor
      if (fieldLabel.includes('editor')) {
        foundEl = widgetEl.querySelector('.elementor-widget-text-editor') || widgetEl;
        if (foundEl) {
          // Gán ID mapping nếu có
          if (fieldId) {
            foundEl.setAttribute('data-mac-html-data-id', fieldId);
            foundEl.setAttribute('mac-html-data-id', fieldId);
            window.macFieldMapping = window.macFieldMapping || {};
            window.macFieldMapping[fieldId] = foundEl;
          }
          foundEl.classList.add('mac-highlight-text');
          foundEl.style.border = '2px solid red';
          foundEl.style.padding = '2px';
          foundEl.scrollIntoView({behavior: 'smooth', block: 'center'});
          window.macCurrentHighlightEl = foundEl;
          window.macCurrentHighlightType = 'editor';
          window.macCurrentHighlightScroll = foundEl;
          // Lưu base-id để update sau này
          window.macCurrentBaseId = baseId;
        }
        return;
      }
      // Nếu là title/text/content
      // Lấy data-index để phân biệt các item trong cùng slide
      const fieldIndex = field.getAttribute('data-index');
      console.log('Looking for text with index:', fieldIndex, 'in slide:', baseId);
      
      // Tìm node text hoặc thẻ con chứa text đúng
      // Ưu tiên tìm node text đúng value (trim), nếu không thì tìm thẻ con có textContent đúng value
      let walker = document.createTreeWalker(widgetEl, NodeFilter.SHOW_TEXT, null, false);
      let foundNode = null;
      let nodeIndex = 0;
      while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.nodeValue && node.nodeValue.trim() === text) {
          // Nếu có fieldIndex, chỉ lấy node tại vị trí đó
          if (fieldIndex === '' || fieldIndex === null || nodeIndex === parseInt(fieldIndex)) {
            foundNode = node;
            console.log('Found node at index:', nodeIndex, 'for field index:', fieldIndex);
            break;
          }
          nodeIndex++;
        }
      }
      if (foundNode) {
        // Chọn thẻ bao phù hợp để gắn ID (ưu tiên title/text container)
        let container = foundNode.parentElement;
        if (container) {
          const fieldType = field.getAttribute('data-field') || '';
          if (fieldType === 'item_title') {
            const t = container.closest && (container.closest('.jet-carousel__item-title') || container.closest('h1, h2, h3, h4, h5, h6'));
            if (t) container = t;
          } else if (fieldType === 'item_text' || fieldType === 'item_comment') {
            const t = container.closest && (container.closest('.jet-carousel__item-text') || container.closest('p'));
            if (t) container = t;
          }
        }

        // Gán ID mapping nếu có
        if (fieldId && container) {
          container.setAttribute('data-mac-html-data-id', fieldId);
          container.setAttribute('mac-html-data-id', fieldId);
          window.macFieldMapping = window.macFieldMapping || {};
          window.macFieldMapping[fieldId] = container;
        }

        // Bọc node text bằng span để border (chỉ để hiển thị)
        const span = document.createElement('span');
        span.className = 'mac-highlight-text';
        span.style.border = '2px solid red';
        // span.style.background = '#fffbe6';
        span.style.padding = '2px';
        foundNode.parentElement.insertBefore(span, foundNode);
        span.appendChild(foundNode);
        span.scrollIntoView({behavior: 'smooth', block: 'center'});
        window.macCurrentHighlightEl = foundNode;
        window.macCurrentHighlightType = 'text';
        window.macCurrentHighlightScroll = span;
        // Lưu base-id để update sau này
        window.macCurrentBaseId = baseId;
        return;
      }
      // Nếu không tìm được node text, thử tìm thẻ con có textContent đúng value
      const tags = widgetEl.querySelectorAll('p,div,span,li,h1,h2,h3,h4,h5,h6,a');
      let tagIndex = 0;
      for (let el of tags) {
        if (el.textContent && el.textContent.trim() === text) {
          // Nếu có fieldIndex, chỉ lấy thẻ tại vị trí đó
          if (fieldIndex === '' || fieldIndex === null || tagIndex === parseInt(fieldIndex)) {
            // Gán ID mapping nếu có
            if (fieldId) {
              el.setAttribute('data-mac-html-data-id', fieldId);
              el.setAttribute('mac-html-data-id', fieldId);
              window.macFieldMapping = window.macFieldMapping || {};
              window.macFieldMapping[fieldId] = el;
            }
            el.classList.add('mac-highlight-text');
            el.style.border = '2px solid red';
            el.style.padding = '2px';
            el.scrollIntoView({behavior: 'smooth', block: 'center'});
            window.macCurrentHighlightEl = el;
            window.macCurrentHighlightType = 'block';
            window.macCurrentHighlightScroll = el;
            // Lưu base-id để update sau này
            window.macCurrentBaseId = baseId;
            console.log('Found tag at index:', tagIndex, 'for field index:', fieldIndex);
            return;
          }
          tagIndex++;
        }
      }
      // Nếu vẫn không tìm được, không đánh dấu gì cả (tránh đánh dấu toàn bộ widget)
      console.log('No specific element found for field:', field.getAttribute('data-field'), 'in slide with base-id:', baseId);
      return;
    }
  }

  // Fallback: tìm theo widgetId cho HTML thường (không phải slick slide)
  if (widgetId) {
    // Tìm element có data-id = widgetId
    const widgetEl = document.querySelector('[data-id="' + widgetId + '"]');
    if (widgetEl) {
      console.log('Found widget by data-id:', widgetId, widgetEl);
      
      // Kiểm tra nếu là carousel widget, tìm slide con dựa trên data-index
      const fieldIndex = field.getAttribute('data-index');
      if (fieldIndex !== '' && fieldIndex !== null) {
        const slides = widgetEl.querySelectorAll('.slick-slide:not(.slick-cloned)');
        const slideIndex = parseInt(fieldIndex);
        if (slides[slideIndex]) {
          const targetSlide = slides[slideIndex];
          console.log('Found target slide at index:', slideIndex, targetSlide);

          // 2) Đường đi xác định theo loại field, không phụ thuộc text
          const fieldType = field.getAttribute('data-field');
          let foundEl = null;
          if (fieldType === 'item_title') {
            foundEl = targetSlide.querySelector('.jet-carousel__item-title, .jet-testimonials__title, .elementor-heading-title, h1, h2, h3, h4, h5, h6');
          } else if (fieldType === 'item_text' || fieldType === 'item_comment') {
            foundEl = targetSlide.querySelector('.jet-carousel__item-text, .jet-testimonials__comment, .elementor-widget-text-editor, p');
          }
          if (foundEl && fieldId) {
            foundEl.setAttribute('data-mac-html-data-id', fieldId);
            foundEl.setAttribute('mac-html-data-id', fieldId);
            window.macFieldMapping = window.macFieldMapping || {};
            window.macFieldMapping[fieldId] = foundEl;
            foundEl.classList.add('mac-highlight-text');
            foundEl.style.border = '2px solid red';
            foundEl.style.padding = '2px';
            foundEl.scrollIntoView({behavior: 'smooth', block: 'center'});
            window.macCurrentHighlightEl = foundEl;
            window.macCurrentHighlightType = 'block';
            window.macCurrentHighlightScroll = foundEl;
            return;
          }
          
          // 3) Nếu không tìm được theo selector, thử theo nội dung text
          foundEl = null;
          
          // Tìm element có nội dung text trùng khớp - ưu tiên thẻ chứa text gần nhất
          const allElements = targetSlide.querySelectorAll('*');
          let bestMatch = null;
          let bestScore = 0;
          
          for (let el of allElements) {
            if (el.textContent && el.textContent.trim() === text) {
              let score = 0;
              
              // Tính điểm dựa trên field type và class/tag phù hợp
              if (fieldType === 'item_title') {
                if (el.classList.contains('jet-carousel__item-title')) score += 100;
                else if (el.tagName.match(/^H[1-6]$/)) score += 80;
                else if (el.closest('.jet-carousel__item-title')) score += 60;
                else if (el.classList.contains('jet-testimonials__title')) score += 90;
                else if (el.classList.contains('title')) score += 70;
                else score += 10; // Điểm cơ bản cho element có text trùng khớp
              } else if (fieldType === 'item_text') {
                if (el.classList.contains('jet-carousel__item-text')) score += 100;
                else if (el.tagName === 'P') score += 80;
                else if (el.closest('.jet-carousel__item-text')) score += 60;
                else if (el.classList.contains('jet-testimonials__comment')) score += 90;
                else if (el.classList.contains('text') || el.classList.contains('content')) score += 70;
                else score += 10; // Điểm cơ bản cho element có text trùng khớp
              } else if (fieldType === 'item_comment') {
                if (el.classList.contains('jet-testimonials__comment')) score += 100;
                else if (el.tagName === 'P') score += 80;
                else if (el.classList.contains('comment') || el.classList.contains('text')) score += 70;
                else score += 10;
              } else {
                // Cho các field khác, ưu tiên element có class phù hợp
                if (el.classList.contains(fieldType)) score += 100;
                else if (el.classList.contains('jet-testimonials__' + fieldType.replace('item_', ''))) score += 90;
                else score += 10;
              }
              
              // Ưu tiên element có ít con nhất (thẻ chứa text gần nhất)
              const childCount = el.querySelectorAll('*').length;
              score += Math.max(0, 50 - childCount); // Càng ít con càng được điểm cao
              
              if (score > bestScore) {
                bestScore = score;
                bestMatch = el;
              }
            }
          }
          
          if (bestMatch) {
            foundEl = bestMatch;
            
            // Tạo ID duy nhất cho element từ field data-id
            const fieldId = field.getAttribute('data-mac-field-data-id');
            foundEl.setAttribute('data-mac-html-data-id', fieldId);
            // Thêm attribute thuần để dễ kiểm tra trực quan theo yêu cầu
            foundEl.setAttribute('mac-html-data-id', fieldId);
            
            // Lưu mapping để update live
            window.macFieldMapping = window.macFieldMapping || {};
            window.macFieldMapping[fieldId] = foundEl;
            
            console.log('Found best match element:', foundEl, 'with score:', bestScore, 'for field type:', fieldType, 'field-id:', fieldId);
          }
          
          // Nếu không tìm thấy theo nội dung, thử tìm theo selector
          if (!foundEl) {
            if (fieldType === 'item_title') {
              foundEl = targetSlide.querySelector('.jet-carousel__item-title, .jet-testimonials__title, .elementor-heading-title, h1, h2, h3, h4, h5, h6');
            } else if (fieldType === 'item_text') {
              foundEl = targetSlide.querySelector('.jet-carousel__item-text, .jet-testimonials__comment, .elementor-widget-text-editor, p');
            } else if (fieldType === 'item_comment') {
              foundEl = targetSlide.querySelector('.jet-testimonials__comment, .comment, p');
            }
            
            // Tạo ID cho element tìm được bằng selector
            if (foundEl) {
              const fieldId = field.getAttribute('data-mac-field-data-id');
              foundEl.setAttribute('data-mac-html-data-id', fieldId);
              foundEl.setAttribute('mac-html-data-id', fieldId);
              
              // Lưu mapping để update live
              window.macFieldMapping = window.macFieldMapping || {};
              window.macFieldMapping[fieldId] = foundEl;
              
              console.log('Found element by selector:', foundEl, 'field-id:', fieldId);
            }
          }
          
          // Nếu vẫn không tìm thấy, thử tìm bất kỳ element nào có text trùng khớp
          if (!foundEl) {
            const allElements = targetSlide.querySelectorAll('*');
            for (let el of allElements) {
              if (el.textContent && el.textContent.trim() === text) {
                foundEl = el;
                
                // Tạo ID cho element tìm được bằng fallback
                const fieldId = field.getAttribute('data-mac-field-data-id');
                foundEl.setAttribute('data-mac-html-data-id', fieldId);
                foundEl.setAttribute('mac-html-data-id', fieldId);
                
                // Lưu mapping để update live
                window.macFieldMapping = window.macFieldMapping || {};
                window.macFieldMapping[fieldId] = foundEl;
                
                console.log('Found element by text content fallback:', el, 'field-id:', fieldId);
                break;
              }
            }
          }
          
          if (foundEl) {
            foundEl.classList.add('mac-highlight-text');
            foundEl.style.border = '2px solid red';
            foundEl.style.padding = '2px';
            foundEl.scrollIntoView({behavior: 'smooth', block: 'center'});
            window.macCurrentHighlightEl = foundEl;
            window.macCurrentHighlightType = 'block';
            window.macCurrentHighlightScroll = foundEl;
            console.log('Found specific element:', foundEl, 'for field type:', fieldType);
            return;
          }
        }
      }
      let foundEl = null;
      // Nếu là editor
      if (fieldLabel.includes('editor')) {
        foundEl = widgetEl.querySelector('.elementor-widget-text-editor') || widgetEl;
        if (foundEl) {
          foundEl.classList.add('mac-highlight-text');
          foundEl.style.border = '2px solid red';
          foundEl.style.padding = '2px';
          foundEl.scrollIntoView({behavior: 'smooth', block: 'center'});
          window.macCurrentHighlightEl = foundEl;
          window.macCurrentHighlightType = 'editor';
          window.macCurrentHighlightScroll = foundEl;
        }
        return;
      }
      // Tìm node text hoặc thẻ con chứa text đúng
      let walker = document.createTreeWalker(widgetEl, NodeFilter.SHOW_TEXT, null, false);
      let foundNode = null;
      while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.nodeValue && node.nodeValue.trim() === text) {
          foundNode = node;
          break;
        }
      }
      if (foundNode) {
        // Bọc node text bằng span để border
        const span = document.createElement('span');
        span.className = 'mac-highlight-text';
        span.style.border = '2px solid red';
        span.style.padding = '2px';
        foundNode.parentElement.insertBefore(span, foundNode);
        span.appendChild(foundNode);
        span.scrollIntoView({behavior: 'smooth', block: 'center'});
        window.macCurrentHighlightEl = foundNode;
        window.macCurrentHighlightType = 'text';
        window.macCurrentHighlightScroll = span;
        return;
      }
      // Nếu không tìm được node text, thử tìm thẻ con có textContent đúng value
      const tags = widgetEl.querySelectorAll('p,div,span,li,h1,h2,h3,h4,h5,h6,a');
      for (let el of tags) {
        if (el.textContent && el.textContent.trim() === text) {
          el.classList.add('mac-highlight-text');
          el.style.border = '2px solid red';
          el.style.padding = '2px';
          el.scrollIntoView({behavior: 'smooth', block: 'center'});
          window.macCurrentHighlightEl = el;
          window.macCurrentHighlightType = 'block';
          window.macCurrentHighlightScroll = el;
          return;
        }
      }
      // Nếu vẫn không tìm được, không đánh dấu gì cả (tránh đánh dấu toàn bộ widget)
      console.log('No specific element found for field:', field.getAttribute('data-field'), 'in widget:', widgetId);
      return;
    }
  }

  // Nếu không có widgetId, fallback logic cũ (tìm toàn trang)
  let found = false;
  let matchCount = 0;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
  while (walker.nextNode()) {
    const node = walker.currentNode;
    if (
      node.nodeValue &&
      node.nodeValue.trim() === text &&
      !form.contains(node.parentElement)
    ) {
      if (matchCount === index) {
        if (node.parentElement.classList.contains('mac-highlight-text')) {
          node.parentElement.scrollIntoView({behavior: 'smooth', block: 'center'});
          node.parentElement.classList.add('mac-highlight-text');
          node.parentElement.style.border = '2px solid red';
          // node.parentElement.style.background = '#fffbe6';
          node.parentElement.style.padding = '2px';
          window.macCurrentHighlightEl = node;
          window.macCurrentHighlightType = 'text';
          window.macCurrentHighlightScroll = node.parentElement;
          found = true;
          break;
        }
        const span = document.createElement('span');
        span.className = 'mac-highlight-text';
        span.style.border = '2px solid red';
        // span.style.background = '#fffbe6';
        span.style.padding = '2px';
        node.parentElement.insertBefore(span, node);
        span.appendChild(node);
        span.scrollIntoView({behavior: 'smooth', block: 'center'});
        window.macCurrentHighlightEl = node;
        window.macCurrentHighlightType = 'text';
        window.macCurrentHighlightScroll = span;
        found = true;
        break;
      }
      matchCount++;
    }
  }
  if (!found) {
    document.querySelectorAll('.mac-highlight-text').forEach(el => {
      el.classList.remove('mac-highlight-text');
      el.style.border = '';
      el.style.background = '';
      el.style.padding = '';
    });
    window.macCurrentHighlightEl = null;
    window.macCurrentHighlightType = null;
    window.macCurrentHighlightScroll = null;
    const tags = document.querySelectorAll('p,div,span,li,h1,h2,h3,h4,h5,h6,a');
    let matchCount2 = 0;
    let foundEl = null;
    for (let el of tags) {
      if (
        el.textContent &&
        el.textContent.replace(/\s+/g, ' ').trim().includes(text) &&
        !form.contains(el)
      ) {
        if (matchCount2 === index) {
          foundEl = el;
          break;
        }
        matchCount2++;
      }
    }
    if (foundEl) {
      foundEl.classList.add('mac-highlight-text');
      foundEl.style.border = '2px solid red';
      // foundEl.style.background = '#fffbe6';
      foundEl.style.padding = '2px';
      foundEl.scrollIntoView({behavior: 'smooth', block: 'center'});
      window.macCurrentHighlightEl = foundEl;
      window.macCurrentHighlightType = 'block';
      window.macCurrentHighlightScroll = foundEl;
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('edit-content-fields-form');
  if (!form || !window.macElementorData) return;
  const jsonData = window.macElementorData;

  // Lắng nghe thay đổi
  form.addEventListener('input', function(e) {
    if (e.target.classList.contains('edit-content-input')) {
      const path = e.target.getAttribute('data-path');
      setByPath(jsonData, path, e.target.value);
      highlightField(e.target, true);
      
      // Update live sử dụng mapping chính xác
      const fieldId = e.target.getAttribute('data-mac-field-data-id');
      
      if (window.macFieldMapping && window.macFieldMapping[fieldId]) {
        const targetElement = window.macFieldMapping[fieldId];
        const newValue = e.target.value;
        
        // Update nội dung element
        if (targetElement.tagName === 'INPUT' || targetElement.tagName === 'TEXTAREA') {
          targetElement.value = newValue;
        } else {
          targetElement.textContent = newValue;
        }
        
        console.log('Updated element live:', targetElement, 'with value:', newValue);
      } else {
        // Fallback: sử dụng logic cũ cho slick slides
        const baseId = e.target.getAttribute('data-mac-base-id');
        if (baseId && window.updateSlidesByBaseId) {
          const field = e.target.getAttribute('data-field');
          const newValue = e.target.value;
          window.updateSlidesByBaseId(baseId, field, newValue);
        }
      }
    }
  });

  // Scroll tới và border đoạn text trên HTML khi focus vào field
  form.addEventListener('focusin', function(e) {
    if (e.target.classList.contains('edit-content-input')) {
      highlightField(e.target, false);
    }
  });

  // Khi blur khỏi field thì xóa border nếu không focus vào field khác
  form.addEventListener('focusout', function(e) {
    setTimeout(function() {
      if (!form.contains(document.activeElement)) {
        document.querySelectorAll('.mac-highlight-text').forEach(el => {
          el.classList.remove('mac-highlight-text');
          el.style.border = '';
          el.style.background = '';
          el.style.padding = '';
        });
        window.macCurrentHighlightEl = null;
        window.macCurrentHighlightType = null;
        window.macCurrentHighlightScroll = null;
      }
    }, 10);
  });

  // Export JSON
  const exportBtn = document.getElementById('export-edit-content-json');
  if (exportBtn) {
    exportBtn.addEventListener('click', function() {
      const dataStr = JSON.stringify(jsonData, null, 2);
      const blob = new Blob([dataStr], {type: 'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'elementor-data-edited.json';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });
  }
}); 
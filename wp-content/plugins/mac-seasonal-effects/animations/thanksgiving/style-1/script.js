document.addEventListener("DOMContentLoaded", function() {
  // Load Lottie Web Component from CDN
  const script = document.createElement('script');
  script.src = 'https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.5/dist/dotlottie-wc.js';
  script.type = 'module';
  
  // Only load if not already loaded
  if (!document.querySelector('script[src*="dotlottie-wc"]')) {
    document.head.appendChild(script);
  }
  
  // Get container and settings
  const container = document.querySelector('.mac-animation-container');
  if (!container) {
    return;
  }
  
  // Get customizable settings from data attributes
  const lottieLeftUrl = container ? (container.getAttribute('data-lottie-left-url') || container.dataset.lottieLeftUrl || 'https://lottie.host/dd5ca33b-939d-4ea0-a026-1a4af4b4b163/qfGfCxpQEx.lottie') : 'https://lottie.host/dd5ca33b-939d-4ea0-a026-1a4af4b4b163/qfGfCxpQEx.lottie';
  const lottieRightUrl = container ? (container.getAttribute('data-lottie-right-url') || container.dataset.lottieRightUrl || 'https://lottie.host/347c66ca-d2a9-4c9e-9d08-f3cc86da6888/towAqDkFp2.lottie') : 'https://lottie.host/347c66ca-d2a9-4c9e-9d08-f3cc86da6888/towAqDkFp2.lottie';
  const lottieSize = container ? parseInt(container.getAttribute('data-lottie-size') || container.dataset.lottieSize || '220') : 220;
  const lottieSizeMobile = container ? parseInt(container.getAttribute('data-lottie-size-mobile') || container.dataset.lottieSizeMobile || '150') : 150;
  const enableLeft = container ? (container.getAttribute('data-enable-left') || container.dataset.enableLeft || 'true') : 'true';
  const enableRight = container ? (container.getAttribute('data-enable-right') || container.dataset.enableRight || 'true') : 'true';
  
  // Set CSS variables for size (desktop and mobile)
  document.documentElement.style.setProperty('--lottie-size', lottieSize + 'px');
  document.documentElement.style.setProperty('--lottie-size-mobile', lottieSizeMobile + 'px');
  
  // Wait for Lottie component to be ready, then set src and show/hide
  function initLottieElements() {
    const leftElement = document.querySelector('.lottie-bottom-left');
    const rightElement = document.querySelector('.lottie-bottom-right');
    
    if (leftElement) {
      // Set src attribute
      leftElement.setAttribute('src', lottieLeftUrl);
      
      // Show/hide based on settings
      if (enableLeft === 'true' || enableLeft === '1' || enableLeft === true) {
        leftElement.style.display = 'block';
      } else {
        leftElement.style.display = 'none';
      }
    }
    
    if (rightElement) {
      // Set src attribute
      rightElement.setAttribute('src', lottieRightUrl);
      
      // Show/hide based on settings
      if (enableRight === 'true' || enableRight === '1' || enableRight === true) {
        rightElement.style.display = 'block';
      } else {
        rightElement.style.display = 'none';
      }
    }
  }
  
  // Try to initialize immediately
  initLottieElements();
  
  // Also try after a short delay to ensure Lottie component is loaded
  setTimeout(initLottieElements, 500);
  
  // Listen for when custom elements are defined
  if (window.customElements) {
    customElements.whenDefined('dotlottie-wc').then(() => {
      initLottieElements();
    }).catch(() => {
      // If custom element is not defined, try again after script loads
      setTimeout(initLottieElements, 1000);
    });
  }
});


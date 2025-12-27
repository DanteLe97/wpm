document.addEventListener("DOMContentLoaded", function() {
  const container = document.querySelector('.mac-animation-container');
  const snowflakesContainer = document.querySelector('.snowflakes');
  
  if (!snowflakesContainer) {
    return;
  }
  
  // Get customizable settings from data attributes or use defaults
  const count = container ? parseInt(container.getAttribute('data-snowflake-count') || container.dataset.snowflakeCount || '30') : 30;
  
  // Get emojis (support up to 3 emojis)
  const emoji1 = container ? (container.getAttribute('data-snowflake-emoji') || container.dataset.snowflakeEmoji || '❅') : '❅';
  const emoji2 = container ? (container.getAttribute('data-snowflake-emoji-2') || container.dataset.snowflakeEmoji2 || '') : '';
  const emoji3 = container ? (container.getAttribute('data-snowflake-emoji-3') || container.dataset.snowflakeEmoji3 || '') : '';
  
  // Build emoji array (filter out empty ones)
  const emojis = [emoji1, emoji2, emoji3].filter(e => e && e.trim() !== '');
  
  // Get other settings
  const color = container ? (container.getAttribute('data-snowflake-color') || container.dataset.snowflakeColor || '#ffffff') : '#ffffff';
  const size = container ? parseInt(container.getAttribute('data-snowflake-size') || container.dataset.snowflakeSize || '16') : 16;
  const fallDuration = container ? parseFloat(container.getAttribute('data-fall-duration') || container.dataset.fallDuration || '10') : 10;
  const shakeDuration = container ? parseFloat(container.getAttribute('data-shake-duration') || container.dataset.shakeDuration || '3') : 3;
  const shakeDistance = container ? parseInt(container.getAttribute('data-shake-distance') || container.dataset.shakeDistance || '80') : 80;
  
  // Set CSS variables on snowflakes container (or document root for global access)
  snowflakesContainer.style.setProperty('--snowflake-color', color);
  snowflakesContainer.style.setProperty('--snowflake-size', size + 'px');
  snowflakesContainer.style.setProperty('--shake-distance', shakeDistance + 'px');
  
  // Clear existing snowflakes
  snowflakesContainer.innerHTML = '';
  
  // Function to create a single snowflake
  function createSnowflake() {
    const snowflake = document.createElement('div');
    snowflake.className = 'snowflake';
    
    // Random select emoji from available emojis
    const selectedEmoji = emojis[Math.floor(Math.random() * emojis.length)];
    snowflake.textContent = selectedEmoji;
    
    // Random position (0% to 100%)
    const leftPercent = Math.random() * 100;
    snowflake.style.left = leftPercent + '%';
    
    // Random animation delays for natural effect
    // Reduce delay range to make it more continuous
    const fallDelay = Math.random() * (fallDuration * 0.3); // Max 30% of fall duration
    const shakeDelay = Math.random() * shakeDuration;
    
    // Set animation durations and delays
    snowflake.style.animationDuration = fallDuration + 's, ' + shakeDuration + 's';
    snowflake.style.animationDelay = fallDelay + 's, ' + shakeDelay + 's';
    snowflake.style.webkitAnimationDuration = fallDuration + 's, ' + shakeDuration + 's';
    snowflake.style.webkitAnimationDelay = fallDelay + 's, ' + shakeDelay + 's';
    
    snowflakesContainer.appendChild(snowflake);
    
    // Remove snowflake after animation completes to free memory
    setTimeout(() => {
      if (snowflake.parentNode) {
        snowflake.remove();
      }
    }, (fallDuration + fallDelay) * 1000 + 1000);
  }
  
  // Create initial batch of snowflakes
  for (let i = 0; i < count; i++) {
    createSnowflake();
  }
  
  // Continuously create new snowflakes to maintain continuous flow
  // Calculate interval: create new snowflake every (fallDuration / count) seconds
  // This ensures there's always snowflakes falling
  const createInterval = (fallDuration * 1000) / count;
  
  setInterval(() => {
    // Only create if container still exists and we don't have too many
    if (snowflakesContainer && snowflakesContainer.children.length < count * 2) {
      createSnowflake();
    }
  }, createInterval);
});


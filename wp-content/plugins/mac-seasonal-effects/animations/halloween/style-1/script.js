document.addEventListener("DOMContentLoaded", function() {
  const pumpkins = [];
  // Get customizable settings from data attribute or use defaults
  const container = document.querySelector('.mac-animation-container');
  const count = container ? parseInt(container.dataset.pumpkinCount || '16') : 16;
  
  // Get emojis (support up to 3 emojis)
  // Use getAttribute to ensure correct reading of data attributes with multiple hyphens
  const emoji1 = container ? (container.getAttribute('data-pumpkin-emoji') || container.dataset.pumpkinEmoji || '🎃') : '🎃';
  const emoji2 = container ? (container.getAttribute('data-pumpkin-emoji-2') || container.dataset.pumpkinEmoji2 || '') : '';
  const emoji3 = container ? (container.getAttribute('data-pumpkin-emoji-3') || container.dataset.pumpkinEmoji3 || '') : '';
  
  // Debug: Log all data attributes to check
  if (container) {
    console.log('MAC Seasonal Effects - Emoji Debug:', {
      'data-pumpkin-emoji': container.getAttribute('data-pumpkin-emoji'),
      'data-pumpkin-emoji-2': container.getAttribute('data-pumpkin-emoji-2'),
      'data-pumpkin-emoji-3': container.getAttribute('data-pumpkin-emoji-3'),
      dataset: {
        pumpkinEmoji: container.dataset.pumpkinEmoji,
        pumpkinEmoji2: container.dataset.pumpkinEmoji2,
        pumpkinEmoji3: container.dataset.pumpkinEmoji3
      },
      allDataAttrs: Object.keys(container.dataset).filter(k => k.includes('emoji'))
    });
  }
  
  // Build emoji array (filter out empty ones)
  const emojis = [emoji1, emoji2, emoji3].filter(e => e && e.trim() !== '');
  
  // Debug: Log final emoji array
  console.log('MAC Seasonal Effects - Final Emojis:', emojis);
  
  // Get size settings
  // Use getAttribute to ensure correct reading of data attributes with multiple hyphens
  const sizeMin = container ? parseInt(container.getAttribute('data-emoji-size-min') || container.dataset.emojiSizeMin || '20') : 20;
  const sizeMax = container ? parseInt(container.getAttribute('data-emoji-size-max') || container.dataset.emojiSizeMax || '50') : 50;
  const randomSizeAttr = container ? (container.getAttribute('data-random-size') || container.dataset.randomSize || 'true') : 'true';
  const randomSize = randomSizeAttr === 'true' || randomSizeAttr === '1' || randomSizeAttr === true;
  
  // Debug: Log size settings
  if (container) {
    console.log('MAC Seasonal Effects - Size Settings:', {
      'data-emoji-size-min': container.getAttribute('data-emoji-size-min'),
      'data-emoji-size-max': container.getAttribute('data-emoji-size-max'),
      'data-random-size': container.getAttribute('data-random-size'),
      parsed: {
        sizeMin: sizeMin,
        sizeMax: sizeMax,
        randomSize: randomSize
      }
    });
  }

  for (let i = 0; i < count; i++) {
    let pumpkin = document.createElement("div");
    
    // Random select emoji from available emojis
    const selectedEmoji = emojis[Math.floor(Math.random() * emojis.length)];
    pumpkin.innerHTML = selectedEmoji;
    
    pumpkin.style.position = "fixed";
    pumpkin.style.left = "0px";
    pumpkin.style.top = "0px";
    
    // Set font size (random or fixed)
    if (randomSize) {
      pumpkin.style.fontSize = (Math.random() * (sizeMax - sizeMin) + sizeMin) + "px";
    } else {
      // Use average size if not random
      pumpkin.style.fontSize = ((sizeMin + sizeMax) / 2) + "px";
    }
    
    pumpkin.style.zIndex = "9998";
    pumpkin.style.pointerEvents = "none";
    document.body.appendChild(pumpkin);

    pumpkins.push({
      el: pumpkin,
      x: Math.random() * window.innerWidth,
      y: Math.random() * -window.innerHeight,
      vx: (Math.random() - 0.5) * 0.3,
      vy: 0.5 + Math.random() * 1.5,
      rot: Math.random() * 360,
      vr: (Math.random() - 0.5) * 2
    });
  }

  let mouseX = -9999, mouseY = -9999;
  document.addEventListener("mousemove", e => {
    mouseX = e.clientX;
    mouseY = e.clientY;
  });

  function animate() {
    const width = window.innerWidth;
    const height = window.innerHeight;

    pumpkins.forEach(p => {
      // Lực né chuột
      const dx = p.x - mouseX;
      const dy = p.y - mouseY;
      const dist = Math.sqrt(dx*dx + dy*dy);

      if (dist < 100) {
        const force = (100 - dist) / 100;
        const angle = Math.atan2(dy, dx);
        p.vx += Math.cos(angle) * force * 1.5;
        p.vy += Math.sin(angle) * force * 1.5;
        p.vr += (Math.random() - 0.5) * 5;
      }

      // Gravity
      p.vy += 0.02;

      // Friction
      p.vx *= 0.98;
      p.vy *= 0.985;
      p.vr *= 0.98;

      // Update vị trí
      p.x += p.vx;
      p.y += p.vy;
      p.rot += p.vr;

      // Reset khi rơi qua màn
      if (p.y > height + 50) {
        p.x = Math.random() * width;
        p.y = -50;
        p.vx = (Math.random() - 0.5) * 0.3;
        p.vy = 0.5 + Math.random() * 1.5;
        p.rot = 0;
        p.vr = (Math.random() - 0.5) * 2;
      }

      // Apply transform
      p.el.style.transform = `translate(${p.x}px, ${p.y}px) rotate(${p.rot}deg)`;
    });

    requestAnimationFrame(animate);
  }

  animate();
});


// Browser entrypoint: loaded with defer by index.html, never as a server handler.
const menuToggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#navigation');
const siteHeader = document.querySelector('.site-header');
function updateHeader() { siteHeader.classList.toggle('scrolled', window.scrollY > 80); }
window.addEventListener('scroll', updateHeader, { passive: true });
updateHeader();
const iconPaths = {
  profile: '<path d="M4 6h16v12H4zM4 6l5-3h15v12l-4 3M9 3v12l-5 3M10 8h6v6h-6z"/>',
  tools: '<path d="M14 5a5 5 0 0 0-6 6L3 16a3 3 0 0 0 4 4l5-5a5 5 0 0 0 7-6l-4 4-4-4 4-4z"/>',
  quality: '<path d="m12 3 3 2 4 1 1 4 2 3-2 3-1 4-4 1-3 2-3-2-4-1-1-4-2-3 2-3 1-4 4-1zM7 13l3 3 7-7"/>',
  phone: '<path d="m7 3-4 2c-1 9 7 17 16 16l2-4-5-3-3 3-6-6 3-3z"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
  pin: '<path d="M19 10c0 5-7 12-7 12S5 15 5 10a7 7 0 1 1 14 0z"/><circle cx="12" cy="10" r="2.5"/>'
};
document.querySelectorAll('[data-icon]').forEach(element => {
  element.innerHTML = `<svg viewBox="0 0 26 26" aria-hidden="true">${iconPaths[element.dataset.icon]}</svg>`;
});
function closeMenus(keep = []) {
  document.querySelectorAll('.dropdown.open').forEach(item => {
    if (keep.includes(item)) return;
    item.classList.remove('open');
    item.querySelector(':scope > button')?.setAttribute('aria-expanded', 'false');
  });
}
menuToggle.addEventListener('click', () => {
  const open = navigation.classList.toggle('open');
  menuToggle.setAttribute('aria-expanded', String(open));
  menuToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
});
document.querySelectorAll('.dropdown > button').forEach(button => {
  button.addEventListener('click', () => {
    const dropdown = button.parentElement;
    if (dropdown.classList.contains('company-dropdown') && matchMedia('(hover: hover) and (min-width: 701px)').matches) return;
    const open = !dropdown.classList.contains('open');
    const ancestors = [];
    let parent = dropdown.parentElement.closest('.dropdown');
    while (parent) { ancestors.push(parent); parent = parent.parentElement.closest('.dropdown'); }
    closeMenus(ancestors);
    dropdown.classList.toggle('open', open);
    button.setAttribute('aria-expanded', String(open));
  });
});
document.addEventListener('click', event => {
  if (!event.target.closest('.dropdown')) closeMenus();
  if (!event.target.closest('.nav-shell')) {
    navigation.classList.remove('open');
    menuToggle.setAttribute('aria-expanded', 'false');
  }
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    const active = document.querySelector('.dropdown.open > button');
    closeMenus();
    if (active) active.focus();
    else if (navigation.classList.contains('open')) menuToggle.focus();
    navigation.classList.remove('open');
    menuToggle.setAttribute('aria-expanded', 'false');
  }
});
navigation.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
  closeMenus();
  navigation.classList.remove('open');
  menuToggle.setAttribute('aria-expanded', 'false');
}));
const industryTrack = document.querySelector('.industries .industry-grid');
if (industryTrack && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const originalCards = Array.from(industryTrack.children);
  originalCards.forEach(card => {
    const clone = card.cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    clone.inert = true;
    industryTrack.appendChild(clone);
  });
  industryTrack.classList.add('auto-scrolling');
  let paused = false;
  let previousTime = 0;
  let loopWidth = 0;
  const measureLoop = () => {
    loopWidth = originalCards.length
      ? industryTrack.children[originalCards.length].offsetLeft - industryTrack.children[0].offsetLeft
      : 0;
  };
  measureLoop();
  window.addEventListener('resize', measureLoop, { passive: true });
  industryTrack.addEventListener('pointerenter', () => { paused = true; });
  industryTrack.addEventListener('pointerleave', () => { paused = false; });
  industryTrack.addEventListener('focusin', () => { paused = true; });
  industryTrack.addEventListener('focusout', event => {
    if (!industryTrack.contains(event.relatedTarget)) paused = false;
  });
  const scrollIndustries = time => {
    if (previousTime && !paused && document.visibilityState === 'visible' && loopWidth > 0) {
      industryTrack.scrollLeft += (time - previousTime) * 0.025;
      if (industryTrack.scrollLeft >= loopWidth) industryTrack.scrollLeft -= loopWidth;
    }
    previousTime = time;
    requestAnimationFrame(scrollIndustries);
  };
  requestAnimationFrame(scrollIndustries);
}
if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const observer = new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
  }), { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(element => { element.classList.add('ready'); observer.observe(element); });
}
const ownerWhatsApp = (document.documentElement.dataset.ownerWhatsapp || '').replace(/\D/g, '');
const contactEmail = document.documentElement.dataset.contactEmail || '';
const prepareInquiryDraft = (form, subject, body, career = false) => {
  const draft = form.querySelector('.email-draft') || document.querySelector('.email-draft');
  if (!draft) return;
  if (ownerWhatsApp) {
    const whatsappUrl = `https://wa.me/${ownerWhatsApp}?text=${encodeURIComponent(`${subject}\n\n${body}`)}`;
    draft.href = whatsappUrl;
    draft.target = '_blank';
    draft.rel = 'noopener noreferrer';
    draft.textContent = 'Open WhatsApp message ↗';
    draft.hidden = false;
    window.open(whatsappUrl, '_blank', 'noopener');
    const status = form.querySelector('.form-status') || document.querySelector('.form-status');
    if (status) status.textContent = 'Your WhatsApp message is ready with the form details.' + (career ? ' Attach your CV in WhatsApp before sending.' : '');
    return;
  }
  if (!contactEmail) {
    const status = form.querySelector('.form-status') || document.querySelector('.form-status');
    if (status) status.textContent = 'Please set an owner WhatsApp number or contact email in the site settings before accepting enquiries.';
    return;
  }
  draft.href = `mailto:${contactEmail}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  draft.removeAttribute('target');
  draft.textContent = 'Open email draft ↗';
  draft.hidden = false;
  const status = form.querySelector('.form-status') || document.querySelector('.form-status');
  if (status) status.textContent = 'Your email draft is ready. Open it below and send it from your email app. Nothing has been sent yet.' + (career ? ' Attach your CV before sending.' : '');
};
const submitInquiry = form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const status = form.querySelector('.form-status');
    const button = form.querySelector('button[type="submit"]');
    const data = new FormData(form);
    data.set('inquiry_type', form.dataset.inquiry || 'Product enquiry');
    data.set('_company', '');
    if (status) status.textContent = 'Sending your submission…';
    if (button) button.disabled = true;
    try {
      const response = await fetch(document.documentElement.dataset.inquiryEndpoint || 'php-backend/submit-inquiry.php', { method: 'POST', body: data, headers: { 'Accept': 'application/json' } });
      const result = await response.json();
      if (status) status.textContent = result.message || (result.ok ? 'Submission sent.' : 'Unable to send submission.');
      if (result.ok) form.reset();
    } catch (_) {
      if (status) status.textContent = 'Could not reach the email service. Please try again or contact us directly.';
    } finally {
      if (button) button.disabled = false;
    }
  });
};
document.querySelectorAll('form[data-inquiry], #quote-form').forEach(submitInquiry);
document.querySelector('#year').textContent = new Date().getFullYear();

// Public enquiry forms prepare drafts; they never report an unsent message as sent.
const inquiryParams = new URLSearchParams(window.location.search);
document.querySelectorAll('form[data-inquiry]').forEach(form => {
  for (const key of ['product', 'role']) {
    const value = inquiryParams.get(key);
    const field = form.elements.namedItem(key);
    if (!value || !field) continue;
    if (field.tagName === 'SELECT' && !Array.from(field.options).some(option => option.value === value)) {
      field.add(new Option(value.slice(0, 150), value.slice(0, 150)));
    }
    field.value = value.slice(0, 150);
  }
});
const catalogueFilter = document.querySelector('[data-product-filter]');
catalogueFilter?.addEventListener('change', () => {
  let count = 0;
  document.querySelectorAll('[data-category]').forEach(card => {
    card.hidden = catalogueFilter.value !== 'all' && card.dataset.category !== catalogueFilter.value;
    if (!card.hidden) { count++; card.classList.add('visible'); }
  });
  document.querySelector('[data-product-count]').textContent = `${count} ${count === 1 ? 'product' : 'products'}`;
});

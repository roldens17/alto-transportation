/* Admin quote decisions: POST with WordPress nonce, no nested forms. */
document.addEventListener('click', (event) => {
  const button = event.target.closest('button.ks-quote-decision');
  if (!button) return;
  const url = new URL(button.dataset.action, window.location.href);
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = url.origin + url.pathname;
  for (const [key, value] of url.searchParams) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    form.appendChild(input);
  }
  document.body.appendChild(form);
  form.submit();
});

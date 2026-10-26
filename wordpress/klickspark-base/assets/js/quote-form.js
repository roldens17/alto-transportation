/* KlickSpark quote form: progressively enhanced REST submission. */
document.addEventListener('submit', async (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.matches('.ks-quote-form')) return;
  event.preventDefault();
  const result = form.querySelector('.ks-quote-result');
  const button = form.querySelector('button[type="submit"]');
  if (!result || !button) return;
  button.disabled = true;
  result.textContent = 'Sending your request…';
  try {
    const response = await fetch(form.action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': form.dataset.ksRestNonce || '' },
      body: new FormData(form),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Could not send your request.');
    result.textContent = data.message;
    form.reset();
  } catch (error) {
    result.textContent = error.message || 'Please try again.';
  } finally {
    button.disabled = false;
  }
});

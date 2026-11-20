document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-search-filter-toggle]')
  if (!btn) return

  e.preventDefault()
  const targetSelector = btn.getAttribute('data-search-filter-toggle')
  const target = document.querySelector(targetSelector)
  if (!target) return

  const isHidden = target.classList.toggle('hidden')
  btn.setAttribute('aria-expanded', (!isHidden).toString())

  const chevron = btn.querySelector('[data-search-filter-chevron]')
  if (chevron) {
    chevron.classList.toggle('-rotate-180', !isHidden)
  }
})

document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-search-filter-reset]')
  if (!btn) return

  const form = btn.closest('form')
  if (!form) return

  form.reset()
  form.querySelectorAll('.js-datepicker').forEach((el) => el._flatpickr?.clear())
})

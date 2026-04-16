/**
 * Dynamic country / city rows for region form (no JSON in browser).
 */
(function () {
  const root = document.getElementById('countries-root');
  const protoCountry = document.getElementById('vp-country-prototype');
  const protoCity = document.getElementById('vp-city-prototype');
  const btnAddCountry = document.getElementById('add-country');
  if (!root || !protoCountry || !protoCity || !btnAddCountry) return;

  function nextCountryIndex() {
    const blocks = root.querySelectorAll('.vp-country-block');
    let max = -1;
    blocks.forEach(function (el) {
      const n = parseInt(el.getAttribute('data-country-index'), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return max + 1;
  }

  function addCountry() {
    const i = nextCountryIndex();
    const html = protoCountry.innerHTML.replace(/__C__/g, String(i));
    root.insertAdjacentHTML('beforeend', html);
  }

  function addCity(countryBlock) {
    const ci = countryBlock.getAttribute('data-country-index');
    const list = countryBlock.querySelector('.vp-cities-list');
    if (!list) return;
    const j = list.querySelectorAll('.vp-city-row').length;
    const html = protoCity.innerHTML.replace(/__C__/g, String(ci)).replace(/__K__/g, String(j));
    list.insertAdjacentHTML('beforeend', html);
  }

  btnAddCountry.addEventListener('click', function () {
    addCountry();
  });

  root.addEventListener('click', function (e) {
    const t = e.target;
    if (t.classList.contains('vp-remove-country')) {
      if (!confirm('Remove this country and all of its cities from this draft?')) return;
      const block = t.closest('.vp-country-block');
      if (block && root.querySelectorAll('.vp-country-block').length > 1) {
        block.remove();
      }
    }
    if (t.classList.contains('vp-add-city')) {
      const block = t.closest('.vp-country-block');
      if (block) addCity(block);
    }
    if (t.classList.contains('vp-remove-city')) {
      if (!confirm('Remove this city from the list?')) return;
      const row = t.closest('.vp-city-row');
      const list = row && row.closest('.vp-cities-list');
      if (row && list && list.querySelectorAll('.vp-city-row').length > 1) {
        row.remove();
      }
    }
  });
})();

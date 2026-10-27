/* ============================================================================
   FairCart — reseller profit calculator
   Works off the real catalogue figures the page rendered into the <option>
   dataset, so nothing here can drift from what the shop actually charges.
   ============================================================================ */
(function () {
  'use strict';

  const product = document.getElementById('calcProduct');
  if (!product) return;

  const tier = document.getElementById('calcTier');
  const qty  = document.getElementById('calcQty');

  const outCost    = document.getElementById('calcCost');
  const outRevenue = document.getElementById('calcRevenue');
  const outProfit  = document.getElementById('calcProfit');
  const note       = document.getElementById('calcNote');

  const rand = (n) => 'R' + n.toLocaleString('en-ZA', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

  function recalc() {
    const opt    = product.selectedOptions[0];
    const retail = parseFloat(opt.dataset.retail);
    const listed = parseFloat(opt.dataset.cost);   // the explicit wholesale price
    const rrp    = parseFloat(opt.dataset.rrp);
    const min    = parseInt(opt.dataset.min, 10) || 1;
    const off    = parseFloat(tier.value);
    const units  = Math.max(1, parseInt(qty.value, 10) || 1);

    // Mirrors Pricing::reseller() — the better of the listed wholesale price
    // and the tier discount off retail, so a higher tier is never worse off.
    const unitCost = Math.min(listed, retail * (1 - off));
    const cost     = unitCost * units;
    const revenue  = rrp * units;
    const profit   = Math.max(0, revenue - cost);
    const marginPct = revenue > 0 ? Math.round((profit / revenue) * 100) : 0;

    outCost.textContent    = rand(cost);
    outRevenue.textContent = rand(revenue);
    outProfit.textContent  = '+' + rand(profit);

    let text = `${units} unit${units === 1 ? '' : 's'} at ${rand(unitCost)} each, sold at the recommended ${rand(rrp)} — `
             + `a ${marginPct}% margin, ${rand(profit / units)} per unit.`;
    if (units < min) {
      text += ` The reseller minimum on this line is ${min} units.`;
    }
    note.textContent = text;
  }

  [product, tier, qty].forEach((el) => {
    el.addEventListener('change', recalc);
    el.addEventListener('input', recalc);
  });
  recalc();
})();

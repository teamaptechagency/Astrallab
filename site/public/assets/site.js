// astrallabs.uk — the only script on the site.
//
// The mobile menu and the care-plan toggle. It used to fill the product grid
// as well, by fetching /api/public/products from the hub — an address taken
// from the plan rather than from the routing table, and one that has never
// existed. So the front page apologised for a catalogue outage that was not
// happening, for as long as anybody had looked at it.
//
// The products are rendered server-side now, by the same call the shop page
// makes: one address to keep in step instead of two, no cross-origin request
// to be blocked, and something in the HTML for readers without JavaScript.
// See pages/home.blade.php.

/* ---- mobile menu ---- */
const toggle = document.querySelector(".nav-toggle");
const nav = document.getElementById("site-nav");

if (toggle && nav) {
  // Hidden is applied here rather than in the HTML so the menu stays visible
  // for anyone whose JS never runs — a broken script must not cost navigation.
  const isMobile = () => window.matchMedia("(max-width: 820px)").matches;
  if (isMobile()) nav.hidden = true;

  toggle.addEventListener("click", () => {
    nav.hidden = !nav.hidden;
    toggle.setAttribute("aria-expanded", String(!nav.hidden));
  });

  window.addEventListener("resize", () => {
    // Crossing the breakpoint resets to that layout's default: collapsed on
    // mobile, always-visible on desktop. Preserving the previous state left
    // the panel stuck open over the page after a rotation or a window resize.
    nav.hidden = isMobile();
    toggle.setAttribute("aria-expanded", String(!isMobile()));
  });
}

/* ---- care plan billing period ---- */
//
// Prices for all three periods live in data attributes on each plan, rather
// than being derived from the monthly figure. A percentage applied in JS would
// produce numbers like ৳4,320 that nobody would actually print on an invoice;
// the discounted prices are decided once and stated plainly.

const billing = document.querySelector(".billing");
const plans = document.querySelectorAll(".plan");

if (billing && plans.length) {
  const LABEL = { monthly: "/month", six: " for 6 months", year: " for a year" };
  const MONTHS = { monthly: 1, six: 6, year: 12 };
  const taka = (n) => `৳${n.toLocaleString("en-US")}`;

  function render(period) {
    for (const plan of plans) {
      const total = Number(plan.dataset[period] || 0);
      plan.querySelector(".plan-amount").textContent = taka(total);
      plan.querySelector(".plan-period").textContent = LABEL[period];

      // On the longer terms, show what it works out to per month — that is the
      // number a shop owner compares against, and hiding it makes the bigger
      // total look worse than it is.
      const equiv = plan.querySelector(".plan-equiv");
      if (period === "monthly") {
        equiv.textContent = "";
      } else {
        const perMonth = Math.round(total / MONTHS[period]);
        const monthly = Number(plan.dataset.monthly || 0);
        const saved = monthly * MONTHS[period] - total;
        equiv.textContent = `${taka(perMonth)}/month · you save ${taka(saved)}`;
      }
    }
  }

  billing.addEventListener("click", (event) => {
    const button = event.target.closest(".billing-opt");
    if (!button) return;

    for (const opt of billing.querySelectorAll(".billing-opt")) {
      const active = opt === button;
      opt.classList.toggle("is-active", active);
      opt.setAttribute("aria-pressed", String(active));
    }
    render(button.dataset.period);
  });

  render("monthly");
}

/* ---- close the mobile menu after choosing something ---- */
//
// Without this the panel stays open over the section it just scrolled to, so
// the visitor's first action after every tap is dismissing a menu.
if (nav) {
  for (const link of nav.querySelectorAll("a")) {
    link.addEventListener("click", () => {
      if (window.matchMedia("(max-width: 820px)").matches) {
        nav.hidden = true;
        toggle?.setAttribute("aria-expanded", "false");
      }
    });
  }
}

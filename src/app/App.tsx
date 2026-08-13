import { useState } from "react";
import { Phone, Plane, Shield, Lock, CheckCircle, ChevronDown, ArrowRight } from "lucide-react";

const ROUTES = [
  { id: "wdw",       label: "Walt Disney World",  code: "MCO→WDW", price: 115 },
  { id: "universal", label: "Universal Orlando",   code: "MCO→UNI", price: 95  },
  { id: "canaveral", label: "Port Canaveral",      code: "MCO→CAN", price: 139 },
  { id: "downtown",  label: "Downtown Orlando",    code: "MCO→ORL", price: 78  },
];

const VEHICLES = [
  { id: "sedan",    short: "Sedan",    label: "Executive Sedan",    surcharge: 0,  pax: "1–3",  bags: "3 bags",           example: "Lincoln Continental" },
  { id: "suv",      short: "SUV",      label: "Premium SUV",        surcharge: 25, pax: "1–6",  bags: "6 bags + car seats",example: "Cadillac Escalade"   },
  { id: "sprinter", short: "Sprinter", label: "Executive Sprinter", surcharge: 65, pax: "7–14", bags: "Unlimited",         example: "Mercedes-Benz Sprinter" },
];

const STEPS = [
  { n: "01", title: "Select Your Route",   body: "Choose from our hand-priced core routes or enter a custom destination. Your quote generates instantly from a locked rate table — not an algorithm." },
  { n: "02", title: "Enter Your Flight",   body: "We pull live FAA arrival data. If your inbound flight is delayed, your chauffeur adjusts automatically at zero extra cost. No calls required." },
  { n: "03", title: "Lock Your Price",     body: "Confirm and pay securely. That number freezes permanently. No traffic multipliers, no late-night spikes, no surprise fees at drop-off." },
  { n: "04", title: "We Meet You Curbside", body: "Your chauffeur monitors your wheels-down time and is at the curb when you clear baggage claim — not a minute early, not a minute late." },
];

const FLEET = [
  {
    code: "SED",
    label: "Executive Sedan",
    example: "Lincoln Continental",
    desc: "Quiet, polished, understated. The right choice for solo travelers and couples who want reliability without excess.",
    specs: [["Passengers", "1–3"], ["Luggage", "3 bags"], ["Car Seats", "On request"]],
    img: "https://images.unsplash.com/photo-1780296269675-169390638617?w=640&h=420&fit=crop&auto=format&q=80",
    imgAlt: "Black luxury sedan in urban setting",
  },
  {
    code: "SUV",
    label: "Premium SUV",
    example: "Cadillac Escalade",
    desc: "Designed for families. Third-row seating, generous cargo capacity, and car seat accommodations built in.",
    specs: [["Passengers", "1–6"], ["Luggage", "6 bags"], ["Car Seats", "Included"]],
    img: "https://images.unsplash.com/photo-1769641241150-26c44a98e17a?w=640&h=420&fit=crop&auto=format&q=80",
    imgAlt: "Dark luxury SUV parked in a lit garage",
  },
  {
    code: "SPR",
    label: "Executive Sprinter",
    example: "Mercedes-Benz Sprinter",
    desc: "Engineered for groups. Conference seating, overhead storage, full standing height, and optional AV package.",
    specs: [["Passengers", "7–14"], ["Luggage", "Unlimited"], ["AV Package", "Available"]],
    img: "https://images.unsplash.com/photo-1700329694402-baa26366b29e?w=640&h=420&fit=crop&auto=format&q=80",
    imgAlt: "Mercedes emblem detail on black vehicle",
  },
];

export default function App() {
  const [selectedRoute,   setSelectedRoute]   = useState(ROUTES[0]);
  const [selectedVehicle, setSelectedVehicle] = useState(VEHICLES[0]);
  const [flightNumber,    setFlightNumber]    = useState("");
  const [date,            setDate]            = useState("");
  const [time,            setTime]            = useState("");
  const [quoted,          setQuoted]          = useState(false);

  const totalPrice = selectedRoute.price + selectedVehicle.surcharge;

  const handleRoute = (id: string) => {
    const r = ROUTES.find(r => r.id === id);
    if (r) { setSelectedRoute(r); setQuoted(false); }
  };

  return (
    <div
      className="min-h-screen bg-background text-foreground"
      style={{ fontFamily: "'DM Sans', system-ui, sans-serif" }}
    >

      {/* ── Navigation ── */}
      <nav className="fixed top-0 left-0 right-0 z-50 bg-primary border-b border-white/10">
        <div className="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
          <div className="flex items-baseline gap-3">
            <span
              className="text-primary-foreground text-2xl tracking-tight"
              style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
            >
              Alto
            </span>
            <span
              className="text-primary-foreground/40 text-[10px] tracking-[0.18em] uppercase hidden sm:inline"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Private Transportation
            </span>
          </div>
          <a
            href="tel:+14075550182"
            className="flex items-center gap-2 text-primary-foreground/70 hover:text-primary-foreground transition-colors"
            style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.8rem" }}
          >
            <Phone size={13} />
            (407) 555-0182
          </a>
        </div>
      </nav>

      {/* ── Hero + Booking Widget ── */}
      <section className="relative pt-16 bg-primary min-h-screen flex items-center">
        {/* Background photo */}
        <div className="absolute inset-0 overflow-hidden bg-primary">
          <img
            src="https://images.unsplash.com/photo-1786434481851-67a153c27c59?w=1600&h=1000&fit=crop&auto=format&q=75"
            alt="Black luxury sedan parked at dusk"
            className="w-full h-full object-cover object-center opacity-[0.18]"
          />
          <div className="absolute inset-0 bg-gradient-to-r from-primary/95 via-primary/80 to-primary/40" />
        </div>

        <div className="relative z-10 max-w-6xl mx-auto px-6 py-24 w-full grid grid-cols-1 lg:grid-cols-[1fr_420px] gap-16 items-start">

          {/* Left: Copy */}
          <div className="pt-2">
            <p
              className="text-accent text-[10px] tracking-[0.22em] uppercase mb-6"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Orlando International Airport · MCO
            </p>
            <h1
              className="text-primary-foreground text-5xl lg:text-6xl leading-[1.08] mb-7"
              style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 800 }}
            >
              The price you see<br />is the price you pay.
            </h1>
            <p className="text-primary-foreground/65 text-lg leading-relaxed mb-10 max-w-[420px]">
              No surge pricing. No traffic multipliers. No late-night spikes.
              If your flight is delayed, your chauffeur adjusts automatically — at zero extra cost.
            </p>
            <div className="flex flex-wrap gap-6">
              {[
                { Icon: Lock,         label: "Price Locked at Booking" },
                { Icon: Plane,        label: "FAA Flight Tracking" },
                { Icon: Shield,       label: "Fully Licensed & Insured" },
              ].map(({ Icon, label }) => (
                <div
                  key={label}
                  className="flex items-center gap-2 text-primary-foreground/50 text-xs"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  <Icon size={13} className="text-accent" strokeWidth={1.75} />
                  {label}
                </div>
              ))}
            </div>
          </div>

          {/* Right: Booking Widget */}
          <div className="rounded-sm overflow-hidden shadow-2xl border border-white/10">
            {/* Widget header */}
            <div className="bg-primary/90 px-6 py-4 border-b border-white/10">
              <p
                className="text-primary-foreground/40 text-[10px] tracking-[0.2em] uppercase mb-0.5"
                style={{ fontFamily: "'JetBrains Mono', monospace" }}
              >
                Book Your Ride
              </p>
              <p className="text-primary-foreground/80 text-sm">
                From:{" "}
                <span
                  className="text-accent"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  MCO — Orlando Intl
                </span>
              </p>
            </div>

            <div className="bg-card p-6 space-y-5">

              {/* Destination */}
              <div>
                <label
                  className="block text-[10px] tracking-[0.18em] uppercase text-muted-foreground mb-1.5"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  Destination
                </label>
                <div className="relative">
                  <select
                    value={selectedRoute.id}
                    onChange={e => handleRoute(e.target.value)}
                    className="w-full border border-border rounded-sm px-3 py-2.5 text-sm text-foreground bg-background appearance-none focus:outline-none focus:ring-1 focus:ring-accent pr-8"
                  >
                    {ROUTES.map(r => (
                      <option key={r.id} value={r.id}>{r.label}</option>
                    ))}
                  </select>
                  <ChevronDown size={13} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                </div>
              </div>

              {/* Date + Time */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label
                    className="block text-[10px] tracking-[0.18em] uppercase text-muted-foreground mb-1.5"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  >
                    Pickup Date
                  </label>
                  <input
                    type="date"
                    value={date}
                    onChange={e => setDate(e.target.value)}
                    className="w-full border border-border rounded-sm px-3 py-2.5 text-sm text-foreground bg-background focus:outline-none focus:ring-1 focus:ring-accent"
                  />
                </div>
                <div>
                  <label
                    className="block text-[10px] tracking-[0.18em] uppercase text-muted-foreground mb-1.5"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  >
                    Pickup Time
                  </label>
                  <input
                    type="time"
                    value={time}
                    onChange={e => setTime(e.target.value)}
                    className="w-full border border-border rounded-sm px-3 py-2.5 text-sm text-foreground bg-background focus:outline-none focus:ring-1 focus:ring-accent"
                  />
                </div>
              </div>

              {/* Flight Number */}
              <div>
                <label
                  className="block text-[10px] tracking-[0.18em] uppercase text-muted-foreground mb-1.5"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  Flight Number{" "}
                  <span className="text-accent normal-case tracking-normal">— we track your arrival</span>
                </label>
                <div className="relative">
                  <Plane size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                  <input
                    type="text"
                    placeholder="AA 1234"
                    value={flightNumber}
                    onChange={e => setFlightNumber(e.target.value)}
                    className="w-full border border-border rounded-sm pl-8 pr-3 py-2.5 text-sm text-foreground bg-background focus:outline-none focus:ring-1 focus:ring-accent"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  />
                </div>
              </div>

              {/* Vehicle */}
              <div>
                <label
                  className="block text-[10px] tracking-[0.18em] uppercase text-muted-foreground mb-1.5"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  Vehicle Class
                </label>
                <div className="grid grid-cols-3 gap-1.5">
                  {VEHICLES.map(v => (
                    <button
                      key={v.id}
                      onClick={() => { setSelectedVehicle(v); setQuoted(false); }}
                      className={`py-2 rounded-sm text-xs border transition-all ${
                        selectedVehicle.id === v.id
                          ? "bg-primary text-primary-foreground border-primary"
                          : "bg-background border-border text-muted-foreground hover:border-primary/40 hover:text-foreground"
                      }`}
                      style={{ fontFamily: "'JetBrains Mono', monospace" }}
                    >
                      {v.short}
                    </button>
                  ))}
                </div>
              </div>

              {/* Price Display */}
              <div className="border border-border rounded-sm p-4 bg-secondary/40">
                <div className="flex items-end justify-between">
                  <div>
                    <p
                      className="text-[10px] tracking-[0.18em] uppercase text-muted-foreground mb-1"
                      style={{ fontFamily: "'JetBrains Mono', monospace" }}
                    >
                      Locked Price
                    </p>
                    <p
                      className="text-4xl text-foreground tabular-nums leading-none"
                      style={{ fontFamily: "'JetBrains Mono', monospace", fontWeight: 500 }}
                    >
                      ${totalPrice}
                    </p>
                    <p
                      className="text-[11px] text-muted-foreground mt-1.5"
                      style={{ fontFamily: "'JetBrains Mono', monospace" }}
                    >
                      {selectedRoute.code} · {selectedVehicle.short}
                    </p>
                  </div>
                  <div className="flex flex-col items-end gap-1.5">
                    <span
                      className="inline-flex items-center gap-1 bg-accent/10 text-accent text-[10px] px-2 py-1 rounded-sm tracking-widest"
                      style={{ fontFamily: "'JetBrains Mono', monospace" }}
                    >
                      <Lock size={9} />
                      NO SURGE
                    </span>
                    <span
                      className="inline-flex items-center gap-1 text-muted-foreground text-[10px] px-2 py-1 rounded-sm tracking-widest"
                      style={{ fontFamily: "'JetBrains Mono', monospace" }}
                    >
                      <Plane size={9} />
                      FLIGHT TRACKED
                    </span>
                  </div>
                </div>
              </div>

              {/* CTA */}
              <button
                onClick={() => setQuoted(true)}
                className="w-full bg-accent hover:bg-accent/90 text-accent-foreground py-3.5 rounded-sm font-medium tracking-wide transition-colors flex items-center justify-center gap-2 text-sm"
              >
                {quoted ? (
                  <>
                    <CheckCircle size={15} />
                    Price Locked — Continue to Checkout
                  </>
                ) : (
                  <>
                    Lock My Price
                    <ArrowRight size={15} />
                  </>
                )}
              </button>

              <p
                className="text-center text-muted-foreground text-[10px] tracking-wide"
                style={{ fontFamily: "'JetBrains Mono', monospace" }}
              >
                Secure checkout · 256-bit SSL
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ── Three Promises ── */}
      <section className="py-24 bg-background border-b border-border">
        <div className="max-w-6xl mx-auto px-6">
          <div className="mb-14">
            <p
              className="text-accent text-[10px] tracking-[0.22em] uppercase mb-3"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              The Alto Standard
            </p>
            <h2
              className="text-foreground text-4xl"
              style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
            >
              Three promises. Zero exceptions.
            </h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-px bg-border">
            {[
              {
                n: "01",
                Icon: Lock,
                title: "Price Lock",
                heading: "Your quote is a contract.",
                body: "When you confirm, that number freezes. Traffic, weather, time of day — none of it changes what you owe. Every price on this site is set in tabular monospace because numbers are commitments.",
              },
              {
                n: "02",
                Icon: Plane,
                title: "Flight Tracking",
                heading: "We monitor your FAA arrival.",
                body: "Enter your flight number at booking. Our system pulls live FAA data continuously. If you land two hours late, your chauffeur already knows and has adjusted — no calls, no extra fees.",
              },
              {
                n: "03",
                Icon: Shield,
                title: "Professional Chauffeurs",
                heading: "Background-checked. Licensed. Consistent.",
                body: "Every driver carries full commercial licensing and operates under our insurance umbrella. You get the same standard every trip — not a marketplace lottery.",
              },
            ].map(item => (
              <div key={item.n} className="bg-background p-10 group">
                <div className="flex items-start justify-between mb-6">
                  <item.Icon size={20} className="text-accent" strokeWidth={1.5} />
                  <span
                    className="text-border text-xs tabular-nums"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  >
                    {item.n}
                  </span>
                </div>
                <p
                  className="text-[10px] tracking-[0.18em] uppercase text-muted-foreground mb-3"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  {item.title}
                </p>
                <h3
                  className="text-foreground text-2xl mb-4 leading-snug"
                  style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
                >
                  {item.heading}
                </h3>
                <p className="text-muted-foreground leading-relaxed text-sm">{item.body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Route Pricing Table ── */}
      <section className="py-24 bg-primary">
        <div className="max-w-6xl mx-auto px-6">
          <div className="mb-12">
            <p
              className="text-accent text-[10px] tracking-[0.22em] uppercase mb-3"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Core Routes
            </p>
            <h2
              className="text-primary-foreground text-4xl mb-3"
              style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
            >
              Hand-priced. Permanently locked.
            </h2>
            <p className="text-primary-foreground/50 text-sm max-w-lg">
              These are not estimates. These are the prices you will be charged — confirmed at booking, unchanged at drop-off.
            </p>
          </div>

          {/* Desktop table */}
          <div className="hidden sm:block rounded-sm overflow-hidden border border-white/10">
            <div
              className="grid grid-cols-[1fr_repeat(3,_120px)] bg-white/5 border-b border-white/10 px-6 py-3"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              {["Route", "Sedan", "SUV", "Sprinter"].map(h => (
                <span key={h} className="text-[10px] uppercase tracking-[0.18em] text-primary-foreground/35">{h}</span>
              ))}
            </div>
            {ROUTES.map((route, i) => (
              <div
                key={route.id}
                className={`grid grid-cols-[1fr_repeat(3,_120px)] px-6 py-5 transition-colors hover:bg-white/5 ${
                  i < ROUTES.length - 1 ? "border-b border-white/10" : ""
                }`}
              >
                <div>
                  <p className="text-primary-foreground font-medium text-sm">{route.label}</p>
                  <p
                    className="text-primary-foreground/35 text-[11px] mt-0.5"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  >
                    {route.code}
                  </p>
                </div>
                {[0, 25, 65].map(surcharge => (
                  <p
                    key={surcharge}
                    className="text-primary-foreground text-2xl tabular-nums self-center"
                    style={{ fontFamily: "'JetBrains Mono', monospace", fontWeight: 500 }}
                  >
                    ${route.price + surcharge}
                  </p>
                ))}
              </div>
            ))}
          </div>

          {/* Mobile: stacked route cards */}
          <div className="sm:hidden space-y-3">
            {ROUTES.map(route => (
              <div key={route.id} className="border border-white/10 rounded-sm overflow-hidden">
                <div className="bg-white/5 px-4 py-3 flex items-center justify-between">
                  <div>
                    <p className="text-primary-foreground font-medium text-sm">{route.label}</p>
                    <p className="text-primary-foreground/35 text-[10px] mt-0.5" style={{ fontFamily: "'JetBrains Mono', monospace" }}>{route.code}</p>
                  </div>
                </div>
                <div className="grid grid-cols-3 divide-x divide-white/10">
                  {[["Sedan", 0], ["SUV", 25], ["Sprinter", 65]].map(([label, surcharge]) => (
                    <div key={label as string} className="px-3 py-4 text-center">
                      <p className="text-primary-foreground/40 text-[9px] uppercase tracking-widest mb-1.5" style={{ fontFamily: "'JetBrains Mono', monospace" }}>{label}</p>
                      <p className="text-primary-foreground text-xl tabular-nums" style={{ fontFamily: "'JetBrains Mono', monospace", fontWeight: 500 }}>${route.price + (surcharge as number)}</p>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>

          <p
            className="text-primary-foreground/25 text-[10px] mt-4 tracking-wide"
            style={{ fontFamily: "'JetBrains Mono', monospace" }}
          >
            All prices USD · No gratuity required · No additional fees
          </p>
        </div>
      </section>

      {/* ── Fleet ── */}
      <section className="py-24 bg-background border-b border-border">
        <div className="max-w-6xl mx-auto px-6">
          <div className="mb-14">
            <p
              className="text-accent text-[10px] tracking-[0.22em] uppercase mb-3"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              The Fleet
            </p>
            <h2
              className="text-foreground text-4xl"
              style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
            >
              Three classes. One standard.
            </h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {FLEET.map(v => (
              <div key={v.code} className="border border-border rounded-sm overflow-hidden group bg-card">
                <div className="h-52 overflow-hidden bg-primary relative">
                  <img
                    src={v.img}
                    alt={v.imgAlt}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 opacity-90"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-primary/60 to-transparent" />
                  <span
                    className="absolute top-4 left-4 bg-primary/80 border border-white/10 text-primary-foreground text-[10px] px-2.5 py-1 rounded-sm tracking-[0.18em]"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  >
                    {v.code}
                  </span>
                </div>
                <div className="p-6">
                  <h3
                    className="text-foreground text-xl mb-0.5"
                    style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
                  >
                    {v.label}
                  </h3>
                  <p
                    className="text-muted-foreground text-[11px] mb-4"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  >
                    {v.example}
                  </p>
                  <p className="text-muted-foreground text-sm leading-relaxed mb-5">{v.desc}</p>
                  <div>
                    {v.specs.map(([k, val]) => (
                      <div key={k} className="flex justify-between items-center border-t border-border py-2">
                        <span
                          className="text-[10px] uppercase tracking-[0.16em] text-muted-foreground"
                          style={{ fontFamily: "'JetBrains Mono', monospace" }}
                        >
                          {k}
                        </span>
                        <span
                          className="text-[11px] text-foreground"
                          style={{ fontFamily: "'JetBrains Mono', monospace" }}
                        >
                          {val}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── How It Works ── */}
      <section className="py-24 bg-secondary/50 border-b border-border">
        <div className="max-w-6xl mx-auto px-6">
          <div className="mb-14">
            <p
              className="text-accent text-[10px] tracking-[0.22em] uppercase mb-3"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Process
            </p>
            <h2
              className="text-foreground text-4xl"
              style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
            >
              Four steps to certainty.
            </h2>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">
            {STEPS.map(step => (
              <div key={step.n}>
                <p
                  className="text-5xl tabular-nums leading-none mb-5"
                  style={{
                    fontFamily: "'JetBrains Mono', monospace",
                    fontWeight: 400,
                    color: "var(--border)",
                    opacity: 1,
                  }}
                >
                  {step.n}
                </p>
                <h3
                  className="text-foreground text-lg mb-3"
                  style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
                >
                  {step.title}
                </h3>
                <p className="text-muted-foreground text-sm leading-relaxed">{step.body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Trust Bar ── */}
      <section className="py-10 bg-background border-b border-border">
        <div className="max-w-6xl mx-auto px-6">
          <div className="flex flex-wrap items-center justify-center gap-8 lg:gap-14">
            {[
              { label: "FL DHSMV Licensed",    sub: "Transport for Hire" },
              { label: "Commercial Insurance", sub: "$5M Liability" },
              { label: "Secure Checkout",      sub: "256-bit SSL" },
              { label: "No Hidden Fees",       sub: "Price shown = price paid" },
              { label: "24/7 Dispatch",        sub: "(407) 555-0182" },
            ].map(item => (
              <div key={item.label} className="text-center">
                <p
                  className="text-foreground text-[10px] uppercase tracking-[0.16em]"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  {item.label}
                </p>
                <p
                  className="text-muted-foreground text-[10px] mt-0.5"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  {item.sub}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Footer ── */}
      <footer className="bg-primary text-primary-foreground py-16 pb-28 md:pb-16">
        <div className="max-w-6xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-12">
          <div>
            <p
              className="text-primary-foreground text-2xl mb-1"
              style={{ fontFamily: "'Outfit', sans-serif", fontWeight: 700 }}
            >
              Alto
            </p>
            <p
              className="text-primary-foreground/35 text-[10px] uppercase tracking-[0.2em] mb-5"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Private Transportation
            </p>
            <p className="text-primary-foreground/55 text-sm leading-relaxed">
              Predictable travel, engineered for relief. Serving Orlando International Airport and all Central Florida destinations.
            </p>
          </div>

          <div>
            <p
              className="text-primary-foreground/35 text-[10px] uppercase tracking-[0.18em] mb-5"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Core Routes
            </p>
            <div className="space-y-3">
              {ROUTES.map(r => (
                <div key={r.id} className="flex items-center justify-between">
                  <span className="text-primary-foreground/65 text-sm">{r.label}</span>
                  <span
                    className="text-primary-foreground tabular-nums text-sm"
                    style={{ fontFamily: "'JetBrains Mono', monospace" }}
                  >
                    ${r.price}
                  </span>
                </div>
              ))}
            </div>
          </div>

          <div>
            <p
              className="text-primary-foreground/35 text-[10px] uppercase tracking-[0.18em] mb-5"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Dispatch
            </p>
            <a
              href="tel:+14075550182"
              className="flex items-center gap-2.5 text-primary-foreground hover:text-accent transition-colors mb-2"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              <Phone size={13} />
              (407) 555-0182
            </a>
            <p
              className="text-primary-foreground/35 text-[11px]"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Available 24 hours · 7 days
            </p>
            <p
              className="text-primary-foreground/25 text-[10px] mt-8"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              © 2024 Alto Private Transportation
            </p>
          </div>
        </div>
      </footer>

      {/* ── Sticky Mobile Action Bar ── */}
      <div className="fixed bottom-0 left-0 right-0 z-50 md:hidden bg-primary/95 backdrop-blur-sm border-t border-white/10 px-4 py-3 flex gap-3">
        <button
          onClick={() => setQuoted(true)}
          className="flex-1 bg-accent hover:bg-accent/90 text-accent-foreground py-3 rounded-sm text-sm font-medium flex items-center justify-center gap-2 transition-colors"
        >
          <Lock size={14} />
          Book Online
        </button>
        <a
          href="tel:+14075550182"
          className="flex-1 border border-white/20 text-primary-foreground py-3 rounded-sm text-sm flex items-center justify-center gap-2 hover:bg-white/10 transition-colors"
          style={{ fontFamily: "'JetBrains Mono', monospace" }}
        >
          <Phone size={14} />
          Call Dispatch
        </a>
      </div>

    </div>
  );
}

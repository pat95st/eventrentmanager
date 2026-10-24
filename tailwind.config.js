/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class', // umschaltbar per Button (siehe resources/views/layout.php), nicht nur Systemeinstellung
  content: [
    './resources/views/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        // Werte kommen NICHT mehr fest kodiert, sondern über CSS-Variablen
        // (siehe resources/css/app.css, Abschnitt "Farbschema"), damit die
        // Farbschema-Auswahl (settings/index.php, data-scheme-Attribut auf
        // <html>) tatsächlich wirkt: Tailwind bäckt Utility-Klassen wie
        // bg-brand-600 sonst zur Build-Zeit auf einen FESTEN Hex-Wert, den
        // kein Laufzeit-Attribut mehr ändern kann. Das "<alpha-value>"-Muster
        // ist Tailwinds empfohlener Weg, CSS-Variablen als Farbwert
        // einzubinden und dabei Opacity-Utilities (bg-brand-600/50 usw.)
        // weiterhin funktionsfähig zu halten. Die Variablen selbst liegen
        // als "R G B"-Tripel vor (nicht als Hex), das verlangt dieses Muster.
        brand: {
          50: 'rgb(var(--color-brand-50) / <alpha-value>)',
          100: 'rgb(var(--color-brand-100) / <alpha-value>)',
          200: 'rgb(var(--color-brand-200) / <alpha-value>)',
          300: 'rgb(var(--color-brand-300) / <alpha-value>)',
          400: 'rgb(var(--color-brand-400) / <alpha-value>)',
          500: 'rgb(var(--color-brand-500) / <alpha-value>)',
          600: 'rgb(var(--color-brand-600) / <alpha-value>)',
          700: 'rgb(var(--color-brand-700) / <alpha-value>)',
          800: 'rgb(var(--color-brand-800) / <alpha-value>)',
          900: 'rgb(var(--color-brand-900) / <alpha-value>)',
          950: 'rgb(var(--color-brand-950) / <alpha-value>)',
        },
      },
    },
  },
  plugins: [],
};

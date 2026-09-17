import { Link } from 'react-router-dom';
import { MANUALS } from './panduan/content';

/**
 * Panduan Pengguna Landing Page
 * 
 * Halaman utama untuk memilih panduan pengguna MGE-PMS.
 * Ini menggantikan halaman dokumentasi lengkap dengan carian dan sidebar.
 */

export default function Panduan() {
  return (
    <div className="min-h-screen bg-slate-50 text-slate-800">
      {/* Top bar */}
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
          <div className="flex items-center gap-3">
            <img src="/logo.png" alt="MGE-PMS" className="h-9 w-9 rounded-lg object-contain" />
            <div>
              <p className="text-sm font-bold leading-tight text-slate-900">MGE-PMS</p>
              <p className="text-xs text-slate-500">Panduan Pengguna</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Link to="/login" className="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">Log Masuk</Link>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-6xl px-4 py-8">
        {/* Hero */}
        <div className="mb-8">
          <div className="border-t-2 border-emerald-600 pt-4"></div>
          <h1 className="mt-4 text-3xl font-semibold tracking-tight text-slate-900">Panduan Pengguna</h1>
          <p className="mt-2 text-slate-600">Pilih panduan yang anda perlukan.</p>
        </div>

        {/* Manuals Grid */}
        <div className="grid gap-6 sm:grid-cols-2">
          {Object.entries(MANUALS).map(([key, m]) => (
            <Link to={`/panduan/${key}`} key={key} className="block rounded-2xl bg-white p-8 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-emerald-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500">
              <p className="text-xs font-semibold tracking-wider text-emerald-600">{m.eyebrow}</p>
              <h2 className="mt-2 text-xl font-semibold text-slate-900">{m.cardTitle}</h2>
              <p className="mt-2 text-sm leading-relaxed text-slate-600">{m.cardDescription}</p>
              <p className="mt-4 text-xs text-slate-500">{m.cardTopics.join(' · ')}</p>
              <span className="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-emerald-600">Buka panduan <span aria-hidden>→</span></span>
            </Link>
          ))}
        </div>

        {/* Footer */}
        <footer className="mt-12 border-t border-slate-200 pt-6 text-center text-xs text-slate-400">
          © {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd. ·{' '}
          <a href="https://mge-eng.com" target="_blank" rel="noopener noreferrer" className="font-semibold text-emerald-600 hover:text-emerald-700">mge-eng.com</a>
          {' '}·{' '}
          <Link to="/login" className="font-semibold text-emerald-600 hover:text-emerald-700">Log Masuk</Link>
        </footer>
      </div>
    </div>
  );
}

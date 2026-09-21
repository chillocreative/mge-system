import { Field, Area } from './fields';

/**
 * FormPaper — the shared letterhead + client/contractor header chrome that
 * wraps every Site Form layout. `page` is 1-based; the client/contractor
 * header block only renders on page 1 (subsequent pages start straight into
 * the layout's own content).
 */
export default function FormPaper({ meta, page = 1, totalPages, children }) {
    const pages = totalPages ?? meta?.pages ?? 1;
    const isQf = meta?.labels === 'qf';
    const docNoLabel = isQf ? 'No. Form' : 'Document No.';
    const revisionLabel = isQf ? 'No. Revision' : 'Revision No.';
    const effectiveLabel = isQf ? 'Commencement Date' : 'Effective Date';

    return (
        <div className="site-form-paper mx-auto w-full max-w-[210mm] bg-white p-6 text-[11px] leading-tight text-gray-900 shadow-md ring-1 ring-gray-300 print:max-w-none print:p-0 print:shadow-none print:ring-0">
            {/* Letterhead */}
            <table className="w-full border-collapse border border-black text-[11px]">
                <tbody>
                    <tr>
                        <td className="w-24 border border-black p-2 align-middle text-center">
                            <img src="/logo.png" alt="MGE" className="mx-auto h-14 object-contain" />
                        </td>
                        <td className="border border-black p-2 text-center align-middle">
                            <p className="font-bold">MULTI GREEN ENGINEERING SDN. BHD.</p>
                            <p>(201401009201) (1085279-D)</p>
                            <p className="mt-1 font-bold uppercase">{meta?.title}</p>
                            {meta?.subtitle && <p className="font-semibold">{meta.subtitle}</p>}
                            {meta?.subtitle2 && <p className="font-semibold">{meta.subtitle2}</p>}
                        </td>
                        <td className="w-56 border border-black p-2 align-top text-[10.5px]">
                            <p>{docNoLabel} : {meta?.docNo}</p>
                            <p>{revisionLabel} : {meta?.revision}</p>
                            <p>{effectiveLabel} : {meta?.effectiveDate}</p>
                            <p>Page : {page}/{pages}</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            {page === 1 && (
                <table className="w-full border-collapse border border-t-0 border-black text-[11px]">
                    <tbody>
                        <tr>
                            <td className="w-1/2 border border-black px-1.5 py-1 align-top">
                                <p className="font-semibold uppercase">Pemilik (Client)</p>
                                <Field path="header.client" />
                            </td>
                            <td className="w-1/2 border border-black px-1.5 py-1 align-top">
                                <p className="font-semibold uppercase">Kontraktor Utama (Main Contractor)</p>
                                <Field path="header.contractor" placeholder="MULTI GREEN ENGINEERING SDN. BHD." />
                            </td>
                        </tr>
                        <tr>
                            <td colSpan={2} className="border border-black px-1.5 py-1 align-top">
                                <div className="flex items-start gap-1">
                                    <span className="font-semibold whitespace-nowrap">PROJECT: </span>
                                    <div className="min-w-0 flex-1"><Area path="header.project" rows={2} /></div>
                                </div>
                                <div className="mt-1 flex items-center gap-6">
                                    <div className="flex min-w-0 flex-1 items-center gap-1">
                                        <span className="font-semibold whitespace-nowrap">CONTRACT NO.: </span>
                                        <div className="min-w-0 flex-1"><Field path="header.contract_no" /></div>
                                    </div>
                                    {isQf && (
                                        <div className="flex min-w-0 flex-1 items-center gap-1">
                                            <span className="font-semibold whitespace-nowrap">REF. NO.: </span>
                                            <div className="min-w-0 flex-1"><Field path="$.ref_no" /></div>
                                        </div>
                                    )}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            )}

            <div className="mt-2">
                {children}
            </div>
        </div>
    );
}

/**
 * MultiPage — renders `pages` FormPaper sheets stacked with a visual gap.
 * `renderPage(pageNumber)` returns the content for that sheet's children.
 */
export function MultiPage({ meta, pages, renderPage }) {
    return (
        <div className="space-y-6">
            {Array.from({ length: pages }, (_, i) => i + 1).map((pageNumber) => (
                <FormPaper key={pageNumber} meta={meta} page={pageNumber} totalPages={pages}>
                    {renderPage(pageNumber)}
                </FormPaper>
            ))}
        </div>
    );
}

import apiClient from '@/services/apiClient';

/**
 * Export helpers for the Site Form viewer. html-to-image/jspdf are lazy-loaded
 * via dynamic import so the main app bundle doesn't carry their weight.
 */

const A4_WIDTH_MM = 210;
const A4_HEIGHT_MM = 297;
const MARGIN_MM = 10;
const MAX_CONTENT_WIDTH_MM = A4_WIDTH_MM - MARGIN_MM * 2; // 190
const MAX_CONTENT_HEIGHT_MM = A4_HEIGHT_MM - MARGIN_MM * 2; // 277

function triggerDownload(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
}

/**
 * Capture every `.site-form-paper` sheet under `root` as a canvas. Strips
 * shadow/ring styling from the cloned document so exports don't carry the
 * on-screen card chrome.
 */
export async function captureSheets(root) {
    if (!root) return [];
    const { toCanvas } = await import('html-to-image');
    const sheets = Array.from(root.querySelectorAll('.site-form-paper'));

    const canvases = [];
    for (const sheet of sheets) {
        const options = {
            pixelRatio: 2,
            backgroundColor: '#ffffff',
            cacheBust: true,
            // Strip the on-screen card chrome from the cloned sheet.
            style: { boxShadow: 'none', margin: '0' },
            filter: (node) => !(node.classList?.contains('print:hidden')),
        };
        // Safari sometimes returns a blank/partial image on the first foreignObject
        // render while fonts and images are still warming up; a second pass is reliable.
        // eslint-disable-next-line no-await-in-loop
        await toCanvas(sheet, options);
        // eslint-disable-next-line no-await-in-loop
        const canvas = await toCanvas(sheet, options);
        canvases.push(canvas);
    }

    return canvases;
}

export function sanitizeBaseName(refNo, id) {
    if (refNo) return String(refNo).trim().replace(/[\s/]+/g, '-');

    return `site-form-${id}`;
}

export function downloadJpg(canvases, baseName) {
    canvases.forEach((canvas, i) => {
        const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
        const filename = canvases.length > 1 ? `${baseName}-p${i + 1}.jpg` : `${baseName}.jpg`;
        triggerDownload(dataUrl, filename);
    });
}

export async function downloadPdf(canvases, baseName) {
    const { default: jsPDF } = await import('jspdf');
    const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });

    canvases.forEach((canvas, i) => {
        if (i > 0) pdf.addPage();

        const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
        const widthMm = MAX_CONTENT_WIDTH_MM;
        let heightMm = (canvas.height * widthMm) / canvas.width;

        let drawWidth = widthMm;
        let drawHeight = heightMm;
        if (heightMm > MAX_CONTENT_HEIGHT_MM) {
            drawHeight = MAX_CONTENT_HEIGHT_MM;
            drawWidth = (canvas.width * drawHeight) / canvas.height;
        }

        pdf.addImage(dataUrl, 'JPEG', MARGIN_MM, MARGIN_MM, drawWidth, drawHeight);
    });

    pdf.save(`${baseName}.pdf`);
}

export async function downloadDocx(canvases, record, baseName) {
    const images = canvases.map((canvas) => canvas.toDataURL('image/jpeg', 0.92));

    const response = await apiClient.post(`/site-forms/${record.id}/export-docx`, { images }, {
        responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([response.data], {
        type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    }));
    triggerDownload(url, `${baseName}.docx`);
    window.URL.revokeObjectURL(url);
}

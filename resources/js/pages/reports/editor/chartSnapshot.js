// Captures a chart section (rendered server-side as SVG) into a PNG blob so it
// can be uploaded as a Word-export asset. The chart endpoint returns
// `image/svg+xml`; we draw it onto a canvas at a fixed size/scale and export
// that canvas as PNG (DOCX embeds raster images, not SVG).
export async function snapshotChartPng(url, { width = 1000, height = 480, scale = 2 } = {}) {
    let response;
    try {
        response = await fetch(url, { credentials: 'include' });
    } catch {
        throw new Error('Could not reach the chart endpoint');
    }
    if (!response.ok) {
        throw new Error(`Chart request failed (${response.status})`);
    }

    const svgText = await response.text();
    const dataUri = `data:image/svg+xml;base64,${btoa(unescape(encodeURIComponent(svgText)))}`;

    const image = await new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Could not render the chart image'));
        img.src = dataUri;
    });

    const canvas = document.createElement('canvas');
    canvas.width = width * scale;
    canvas.height = height * scale;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('Could not export the chart as PNG'));
                return;
            }
            resolve(blob);
        }, 'image/png');
    });
}

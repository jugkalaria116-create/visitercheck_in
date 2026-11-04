/** Downscale big phone photos before upload (faster on mobile data). Server still re-validates everything. */
export async function prepareImage(file, maxSide = 1920) {
  try {
    const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, maxSide / Math.max(bmp.width, bmp.height));
    const w = Math.round(bmp.width * scale);
    const h = Math.round(bmp.height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(bmp, 0, 0, w, h);
    bmp.close?.();
    const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', 0.9));
    if (blob && blob.size > 0) return new File([blob], 'photo.jpg', { type: 'image/jpeg' });
  } catch { /* fall through to the original file */ }
  return file;
}

/** Firestore document size limit (bytes). */
export const FIRESTORE_DOC_MAX = 1_048_576;

/** Default reserve for non-file fields in a document (password hash, names, etc.). */
export const METADATA_RESERVE = 15_000;

function readAsDataURL(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

function loadImage(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Could not read image file.'));
    };
    img.src = url;
  });
}

function canvasToJpeg(img, maxDim, quality) {
  let { width, height } = img;
  const scale = Math.min(1, maxDim / Math.max(width, height));
  width = Math.max(1, Math.round(width * scale));
  height = Math.max(1, Math.round(height * scale));
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(img, 0, 0, width, height);
  return canvas.toDataURL('image/jpeg', quality);
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Max base64 string length per file when multiple files share one Firestore document.
 * @param {number} fileCount - number of file fields stored in the same document
 * @param {number} [reserveBytes] - bytes reserved for other document fields
 */
export function maxBytesPerDocFile(fileCount, reserveBytes = METADATA_RESERVE) {
  const budget = FIRESTORE_DOC_MAX - reserveBytes;
  return Math.floor(budget / Math.max(1, fileCount));
}

/**
 * Read an <input type="file"> value for Firestore storage.
 * Images are compressed; PDFs/other files must fit under maxBytes.
 */
export async function readFileForFirestore(input, maxBytes = maxBytesPerDocFile(1)) {
  const file = input?.files?.[0];
  if (!file) return null;

  if (file.type.startsWith('image/')) {
    const img = await loadImage(file);
    let quality = 0.78;
    let maxDim = 1400;
    for (let attempt = 0; attempt < 14; attempt++) {
      const dataUrl = canvasToJpeg(img, maxDim, quality);
      if (dataUrl.length <= maxBytes) return dataUrl;
      quality = Math.max(0.28, quality - 0.06);
      maxDim = Math.max(400, Math.floor(maxDim * 0.75));
    }
    throw new Error(
      `"${file.name}" is too large even after compression. ` +
        `Use a smaller JPG/PNG photo (under about ${formatSize(maxBytes / 1.37)}).`
    );
  }

  if (file.type === 'application/pdf') {
    throw new Error(
      `"${file.name}" is a PDF. Please upload a JPG or PNG photo of the document instead.`
    );
  }

  const dataUrl = await readAsDataURL(file);
  if (dataUrl.length > maxBytes) {
    throw new Error(
      `"${file.name}" (${formatSize(file.size)}) is too large. ` +
        `Save as JPG/PNG or use a photo under about ${formatSize(maxBytes / 1.37)}.`
    );
  }
  return dataUrl;
}

/**
 * Read multiple file inputs that will be stored in the same Firestore document.
 * Ensures the combined size stays under the document limit.
 */
export async function readFilesForFirestore(inputs, options = {}) {
  const {
    fileCountInDoc = inputs.length,
    reserveBytes = METADATA_RESERVE,
  } = options;
  const perFileMax = maxBytesPerDocFile(fileCountInDoc, reserveBytes);
  const results = [];
  let totalFileBytes = 0;

  for (const input of inputs) {
    const dataUrl = await readFileForFirestore(input, perFileMax);
    if (dataUrl) totalFileBytes += dataUrl.length;
    results.push(dataUrl);
  }

  if (totalFileBytes + reserveBytes > FIRESTORE_DOC_MAX) {
    throw new Error(
      'Total upload size exceeds the database limit. ' +
      'Use smaller or clearer photos (JPG/PNG) for each document.'
    );
  }

  return results;
}

/** @deprecated use readFileForFirestore */
export async function readFileAsDataUrl(input) {
  return readFileForFirestore(input);
}

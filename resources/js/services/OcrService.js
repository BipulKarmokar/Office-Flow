import { createWorker } from 'tesseract.js';

export default {
    async scanReceipt(imageFile) {
        const worker = await createWorker('eng');
        const ret = await worker.recognize(imageFile);
        const text = ret.data.text;
        await worker.terminate();
        
        console.log("OCR Text:", text);
        
        // Simple regex to find money amount (e.g., $12.34 or 12.34)
        // Looks for numbers with 2 decimal places, optionally preceded by currency symbol
        const amountRegex = /[$€£¥]?\s*(\d+[.,]\d{2})/;
        const match = text.match(amountRegex);
        
        return {
            text: text,
            amount: match ? parseFloat(match[1].replace(',', '.')) : null
        };
    }
};

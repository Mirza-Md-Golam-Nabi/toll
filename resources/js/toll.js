/**
 * Browser receipt printing: loads the receipt into a hidden iframe and prints it.
 * Unlike window.open() this is never blocked by pop-up blockers. With Chrome started
 * using --kiosk-printing the receipt prints silently to the default (thermal) printer.
 */
(() => {
    const FRAME_ID = 'toll-receipt-frame';

    const printReceipt = (url) => {
        document.getElementById(FRAME_ID)?.remove();

        const frame = document.createElement('iframe');
        frame.id = FRAME_ID;
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        frame.src = url;
        document.body.appendChild(frame);
    };

    window.addEventListener('toll-print-receipt', (event) => {
        const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;

        if (detail?.url) {
            printReceipt(detail.url);
        }
    });
})();

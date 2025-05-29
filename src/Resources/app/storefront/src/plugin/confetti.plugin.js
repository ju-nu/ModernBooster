import Plugin from 'src/plugin-system/plugin.class';

export default class ConfettiPlugin extends Plugin {
    init() {
        // Check if confetti is enabled in plugin configuration
        const confettiEnabled = this.el.dataset.confettiEnabled === 'true';

        if (!confettiEnabled) {
            return;
        }

        // Ensure confetti library is loaded
        if (typeof window.confetti === 'undefined') {
            console.warn('Confetti library not loaded.');
            return;
        }

        // Confetti animation logic
        const duration = 3 * 1000; // 3 seconds
        const animationEnd = Date.now() + duration;
        const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 1000 };

        const randomInRange = (min, max) => Math.random() * (max - min) + min;

        const interval = setInterval(() => {
            const timeLeft = animationEnd - Date.now();
            if (timeLeft <= 0) {
                return clearInterval(interval);
            }
            const particleCount = 75 * (timeLeft / duration);
            window.confetti({
                ...defaults,
                particleCount,
                origin: { x: randomInRange(0.1, 0.9), y: Math.random() - 0.2 },
                colors: ['#ff5555', '#ffaa00', '#55ff55', '#5555ff', '#ff55ff', '#ffff55', '#00ffff', '#ff99cc', '#66ff66', '#ffcc00']
            });
        }, 200);
    }
}
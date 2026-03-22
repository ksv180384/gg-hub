import { ref } from 'vue';

/** optionsGetter вызывается при расчёте результата, чтобы использовать актуальный список. */
export function useSpinWheel(
    optionsGetter: () => string[],
    duration = 4000
) {
    const angle = ref(0);
    const result = ref<string | null>(null);

    const spin = () => {
        const options = optionsGetter();
        if (options.length === 0) return;

        const finalAngle = Math.random() * 360 + 360 * 5;
        const start = performance.now();

        const animate = (now: number) => {
            const progress = Math.min((now - start) / duration, 1);
            const exponent = 3 + Math.min(duration / 5000, 1);
            const ease = 1 - Math.pow(1 - progress, exponent);
            angle.value = ease * finalAngle;

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                angle.value = finalAngle;
                calculateResult();
            }
        };

        requestAnimationFrame(animate);
    };

    const calculateResult = () => {
        const options = optionsGetter();
        if (options.length === 0) {
            result.value = null;
            return;
        }
        // В canvas 270° = верх круга (12 часов). Стрелка указывает туда.
        const topAngle = 270;
        const normalizedAngle = ((topAngle - (angle.value % 360) + 360) % 360);
        const arc = 360 / options.length;
        const selectedIndex = Math.floor(normalizedAngle / arc) % options.length;
        result.value = options[selectedIndex];
    };

    return {
        angle,
        result,
        spin
    };
}

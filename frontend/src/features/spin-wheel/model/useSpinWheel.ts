import { ref } from 'vue';

export function useSpinWheel(options: string[], duration = 4000) {
    const angle = ref(0);
    const result = ref<string | null>(null);

    const spin = () => {
        const finalAngle = Math.random() * 360 + 360 * 5;
        const start = performance.now();

        const animate = (now: number) => {
            const progress = Math.min((now - start) / duration, 1);
            // Лёгкий ease-out: быстрый старт и плавное замедление, но вращение на всю длительность
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

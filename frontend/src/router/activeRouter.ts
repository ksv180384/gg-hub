import type { Router } from 'vue-router';

let activeRouter: Router | null = null;

export function setActiveRouter(router: Router | null): void {
  activeRouter = router;
}

export function getActiveRouter(): Router | null {
  return activeRouter;
}

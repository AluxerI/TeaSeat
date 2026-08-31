import { Component, useEffect, useLayoutEffect, useState, type ComponentProps, type ReactNode } from "react";
import { Canvas, useThree } from "@react-three/fiber";
import { usePageVisibility } from "../../hooks/usePageVisibility";
import { disposeConstructorTextures } from "./three/materials";

class SceneBoundary extends Component<{ children: ReactNode; fallback: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  render() { return this.state.failed ? this.props.fallback : this.props.children; }
}

let sceneUsers = 0;
/** Материалы используют общий кеш: уход одной сцены не освобождает текстуры другой. */
export function useConstructorTextures() {
  useLayoutEffect(() => {
    sceneUsers += 1;
    return () => {
      sceneUsers -= 1;
      queueMicrotask(() => { if (!sceneUsers) disposeConstructorTextures(); });
    };
  }, []);
}

function SceneLifecycle({ visible, onLost }: { visible: boolean; onLost: () => void }) {
  const { gl, invalidate } = useThree();
  useEffect(() => { if (visible) invalidate(); }, [visible, invalidate]);
  useEffect(() => {
    const lost = (event: Event) => { event.preventDefault(); onLost(); };
    gl.domElement.addEventListener("webglcontextlost", lost);
    return () => gl.domElement.removeEventListener("webglcontextlost", lost);
  }, [gl, onLost]);
  return null;
}

/** Canvas перерисовывается только по invalidate; в скрытой вкладке остановлен. */
export default function ConstructorCanvas({ children, fallback, ...props }: Omit<ComponentProps<typeof Canvas>, "fallback"> & { fallback: ReactNode }) {
  const visible = usePageVisibility();
  const [lost, setLost] = useState(false);
  useConstructorTextures();
  return lost ? fallback : <SceneBoundary fallback={fallback}>
    <Canvas {...props} frameloop={visible ? "demand" : "never"} dpr={[1, 1.5]} fallback={fallback}>
      <SceneLifecycle visible={visible} onLost={() => setLost(true)} />
      {children}
    </Canvas>
  </SceneBoundary>;
}

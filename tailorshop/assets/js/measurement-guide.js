const viewer = document.querySelector('[data-measurement-viewer]');

if (viewer) {
    const canvas = viewer.querySelector('canvas');
    const loading = viewer.querySelector('.measurement-guide-loading');
    const fallback = viewer.querySelector('.measurement-guide-error');

    async function loadMannequin() {
        try {
            const [THREE, { GLTFLoader }, { OrbitControls }] = await Promise.all([
                import('three'),
                import('three/addons/loaders/GLTFLoader.js'),
                import('three/addons/controls/OrbitControls.js'),
            ]);

            const renderer = new THREE.WebGLRenderer({
                canvas,
                antialias: true,
                alpha: true,
            });
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
            renderer.outputColorSpace = THREE.SRGBColorSpace;
            renderer.toneMapping = THREE.ACESFilmicToneMapping;
            renderer.toneMappingExposure = 1.15;

            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(32, 1, 0.01, 1000);
            scene.add(new THREE.HemisphereLight(0xffffff, 0x52515a, 2.1));

            const keyLight = new THREE.DirectionalLight(0xffffff, 2.6);
            keyLight.position.set(-3, 5, 6);
            scene.add(keyLight);

            const fillLight = new THREE.DirectionalLight(0xffffff, 1.1);
            fillLight.position.set(4, 1, -4);
            scene.add(fillLight);

            const controls = new OrbitControls(camera, canvas);
            controls.enableDamping = true;
            controls.dampingFactor = 0.06;
            controls.enablePan = false;
            controls.minPolarAngle = 0.15;
            controls.maxPolarAngle = Math.PI - 0.15;

            const gltf = await new GLTFLoader().loadAsync(viewer.dataset.modelSrc);
            const mannequin = new THREE.Group();
            mannequin.add(gltf.scene);
            scene.add(mannequin);

            mannequin.traverse((object) => {
                if (!object.isMesh) return;

                object.material = new THREE.MeshStandardMaterial({
                    color: 0xb8bec6,
                    roughness: 0.88,
                    metalness: 0,
                });
            });

            mannequin.updateMatrixWorld(true);
            const bounds = new THREE.Box3().setFromObject(mannequin);
            const center = bounds.getCenter(new THREE.Vector3());
            const size = bounds.getSize(new THREE.Vector3());
            mannequin.position.sub(center);
            mannequin.updateMatrixWorld(true);
            const mannequinMeshes = [];
            mannequin.traverse((object) => {
                if (object.isMesh) mannequinMeshes.push(object);
            });

            const markerMaterial = new THREE.MeshStandardMaterial({
                color: 0xffe100,
                emissive: 0x8c7600,
                roughness: 0.45,
            });
            const markerRadius = size.y * 0.014;
            const markerGeometry = new THREE.SphereGeometry(markerRadius, 16, 12);
            const frontOffset = size.z * 0.28;
            const raycaster = new THREE.Raycaster();
            const frontZ = size.z / 2 + size.y * 0.1;
            const markerPositions = [
                [0, -size.y / 2 + size.y * 0.69],
                [0, -size.y / 2 + size.y * 0.55],
                [0, -size.y / 2 + size.y * 0.43],
                [-size.x * 0.14, -size.y / 2 + size.y * 0.2],
                [-size.x * 0.08, -size.y / 2 + size.y * 0.81],
                [-size.x * 0.29, -size.y / 2 + size.y * 0.7],
            ];

            markerPositions.forEach(([x, y], index) => {
                raycaster.set(
                    new THREE.Vector3(x, y, frontZ),
                    new THREE.Vector3(0, 0, -1),
                );
                const surface = raycaster.intersectObjects(mannequinMeshes, false)[0];
                const markerZ = surface ? surface.point.z + markerRadius : frontOffset;
                const marker = new THREE.Mesh(markerGeometry, markerMaterial);
                marker.position.set(x + center.x, y + center.y, markerZ + center.z);
                mannequin.add(marker);

                const labelCanvas = document.createElement('canvas');
                labelCanvas.width = 64;
                labelCanvas.height = 64;
                const context = labelCanvas.getContext('2d');
                context.fillStyle = '#ffe100';
                context.beginPath();
                context.arc(32, 32, 29, 0, Math.PI * 2);
                context.fill();
                context.fillStyle = '#171717';
                context.font = 'bold 34px Arial';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillText(String(index + 1), 32, 34);

                const labelTexture = new THREE.CanvasTexture(labelCanvas);
                labelTexture.colorSpace = THREE.SRGBColorSpace;
                const label = new THREE.Sprite(new THREE.SpriteMaterial({ map: labelTexture }));
                label.position.set(
                    x + center.x,
                    y + markerRadius * 2.1 + center.y,
                    markerZ + markerRadius + center.z,
                );
                label.scale.set(markerRadius * 4, markerRadius * 4, 1);
                mannequin.add(label);
            });

            const height = size.y;
            camera.position.set(0, 0, height);
            camera.near = Math.max(height / 1000, 0.01);
            camera.far = height * 20;
            camera.updateProjectionMatrix();
            controls.target.set(0, 0, 0);
            controls.minDistance = height * 0.8;
            controls.maxDistance = height * 8;
            controls.update();

            const resizeObserver = new ResizeObserver(() => {
                const { width, height: viewportHeight } = viewer.getBoundingClientRect();
                if (!width || !viewportHeight) return;

                renderer.setSize(width, viewportHeight, false);
                camera.aspect = width / viewportHeight;
                camera.updateProjectionMatrix();

                const halfVerticalFov = THREE.MathUtils.degToRad(camera.fov / 2);
                const halfWidth = Math.max(size.x / 2, size.y * camera.aspect / 2);
                const fitDistance = (
                    (halfWidth / camera.aspect) / Math.tan(halfVerticalFov) + size.z / 2
                ) * 1.15;
                const offset = camera.position.clone().sub(controls.target);
                const currentDistance = offset.length();

                if (currentDistance < fitDistance) {
                    camera.position.copy(controls.target).add(
                        offset.normalize().multiplyScalar(fitDistance),
                    );
                }

                controls.maxDistance = Math.max(height * 8, fitDistance * 1.5);
                controls.update();
            });
            resizeObserver.observe(viewer);

            loading.hidden = true;
            renderer.setAnimationLoop(() => {
                controls.update();
                renderer.render(scene, camera);
            });
        } catch (error) {
            console.error('Unable to load the tailoring measurement mannequin.', error);
            loading.hidden = true;
            fallback.hidden = false;
        }
    }

    loadMannequin();
}

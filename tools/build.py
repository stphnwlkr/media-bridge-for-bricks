from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import shutil
import tempfile

root = Path(__file__).resolve().parents[1]
output = root / 'builds' / 'media-bridge-for-bricks-1.0.0.zip'
output.parent.mkdir(exist_ok=True)
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for name in ('media-bridge-for-bricks.php', 'readme.txt', 'LICENSE', 'includes', 'assets', 'blocks'):
        path = root / name
        files = sorted(path.rglob('*')) if path.is_dir() else [path]
        for file in files:
            if file.is_file():
                archive.write(file, Path('media-bridge-for-bricks') / file.relative_to(root))
print(output)

# Keep the local test installation pointed at distribution files, not source-only tests/builds.
runtime_parent = root / 'builds' / 'test-site'
runtime_parent.mkdir(exist_ok=True)
runtime = runtime_parent / 'media-bridge-for-bricks'
previous = runtime_parent / '.previous'
if runtime.is_symlink() or previous.is_symlink():
    raise RuntimeError('Refusing to replace a symlink in the generated runtime directory.')
with tempfile.TemporaryDirectory(prefix='.stage-', dir=runtime_parent) as staging:
    with ZipFile(output) as archive:
        archive.extractall(staging)
    if previous.exists():
        shutil.rmtree(previous)
    if runtime.exists():
        runtime.rename(previous)
    (Path(staging) / 'media-bridge-for-bricks').rename(runtime)
    if previous.exists():
        shutil.rmtree(previous)
print(runtime)

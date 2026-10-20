from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parents[1]
output = root / 'builds' / 'media-bridge-for-bricks-1.0.0-beta.15.zip'
output.parent.mkdir(exist_ok=True)
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for name in ('media-bridge-for-bricks.php', 'readme.txt', 'LICENSE', 'includes', 'assets', 'blocks'):
        path = root / name
        files = sorted(path.rglob('*')) if path.is_dir() else [path]
        for file in files:
            if file.is_file():
                archive.write(file, Path('media-bridge-for-bricks') / file.relative_to(root))
print(output)

from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import re
root = Path(__file__).resolve().parents[1]
version = re.search(r"Version: ([0-9.]+)", (root/'media-bridge-for-bricks.php').read_text()).group(1)
out = root/'builds'/f'uplink-media-manager-{version}.zip'
out.parent.mkdir(exist_ok=True)
with ZipFile(out, 'w', ZIP_DEFLATED) as archive:
    for name in ('media-bridge-for-bricks.php', 'readme.txt', 'LICENSE', 'includes', 'assets', 'blocks'):
        path = root/name
        for file in sorted(path.rglob('*')) if path.is_dir() else [path]:
            if file.is_file(): archive.write(file, Path('uplink-media-manager')/file.relative_to(root))
print(out)

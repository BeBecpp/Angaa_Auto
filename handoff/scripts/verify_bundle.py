#!/usr/bin/env python3
"""Verify bundled file integrity with Python 3 standard library only."""
import hashlib
import json
import sys
from pathlib import Path


def main():
    root = Path(__file__).resolve().parent.parent
    manifest = json.loads((root / 'MANIFEST.json').read_text(encoding='utf-8'))
    failures = []
    for item in manifest['files']:
        relative = Path(item['path'])
        path = root / relative
        if relative.is_absolute() or '..' in relative.parts or path.is_symlink():
            failures.append(str(relative) + ': unsafe path')
            continue
        if not path.is_file():
            failures.append(str(relative) + ': missing')
            continue
        data = path.read_bytes()
        if len(data) != item['bytes'] or hashlib.sha256(data).hexdigest() != item['sha256']:
            failures.append(str(relative) + ': content differs')
    if failures:
        print('\n'.join(failures), file=sys.stderr)
        return 1
    print(f"Verified {len(manifest['files'])} files. This checks bundle integrity, not Laravel application behavior.")
    return 0


if __name__ == '__main__':
    raise SystemExit(main())

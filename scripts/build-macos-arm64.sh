#!/bin/zsh

set -euo pipefail

script_dir=${0:A:h}
project_dir=${script_dir:h}
dist_dir="$project_dir/nativephp/electron/dist"

if [[ $(uname -s) != Darwin || $(uname -m) != arm64 ]]; then
    print -u2 "Treehouse's Apple Silicon DMG must be built on an arm64 Mac."
    exit 1
fi

cd "$project_dir"

php artisan config:clear
composer audit --locked --no-dev
php artisan test --compact
npm run build
npm --prefix "$project_dir/nativephp/electron" audit --audit-level=high
php artisan native:build mac arm64 --no-interaction

artifacts=("$dist_dir"/Treehouse-*-arm64.dmg(Nom))

if (( ${#artifacts[@]} == 0 )); then
    print -u2 "NativePHP completed without producing an Apple Silicon DMG."
    exit 1
fi

artifact=${artifacts[-1]}
app_bundle="$dist_dir/mac-arm64/Treehouse.app"

hdiutil verify "$artifact"
file "$artifact"
(cd "$dist_dir" && shasum -a 256 "${artifact:t}") > "$artifact.sha256"
cat "$artifact.sha256"

if codesign --verify --deep --strict "$app_bundle" >/dev/null 2>&1; then
    codesign --verify --deep --strict --verbose=2 "$app_bundle"
    spctl --assess --type execute --verbose=2 "$app_bundle"
    xcrun stapler validate "$app_bundle"
else
    print -u2 'WARNING: no valid Developer ID signature was produced; this is a development DMG and will not pass Gatekeeper on another Mac.'
fi

print "Built $artifact"
print "Checksum $artifact.sha256"

#!/usr/bin/env python3
"""
Full dataeksport fra Base44 til én JSON-fil per entitet (grunnlag for php artisan base44:import).

Bruk:  BASE44_API_KEY=... python3 export_base44.py <app_id> <ut-mappe> [Entity ...]

Nøkkelen lages i Base44 under Settings → API keys (service role). Lagres ikke i repoet.
Skriver også counts.json med antall rader per entitet = avstemmingsgrunnlaget ved cutover.
"""
import json, os, sys, time, urllib.request, urllib.parse

API = os.environ.get('BASE44_API_URL', 'https://app.base44.com/api')
KEY = os.environ.get('BASE44_API_KEY') or sys.exit('Sett BASE44_API_KEY')

def get(path, params=None):
    url = f"{API}{path}" + (('?' + urllib.parse.urlencode(params)) if params else '')
    req = urllib.request.Request(url, headers={'api_key': KEY, 'Accept': 'application/json'})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.load(r)

def export_entity(app_id, entity, out_dir, page=500):
    rows, skip = [], 0
    while True:
        batch = get(f"/apps/{app_id}/entities/{entity}", {'limit': page, 'skip': skip, 'sort': 'created_date'})
        rows.extend(batch)
        if len(batch) < page:
            break
        skip += page
        time.sleep(0.2)
    with open(os.path.join(out_dir, f"{entity}.json"), 'w', encoding='utf-8') as f:
        json.dump(rows, f, ensure_ascii=False)
    return len(rows)

def main():
    app_id, out_dir, *only = sys.argv[1:]
    os.makedirs(out_dir, exist_ok=True)
    schemas = get(f"/apps/{app_id}/entities")   # liste over entiteter
    names = only or sorted(s['name'] if isinstance(s, dict) else s for s in schemas)
    counts = {}
    for n in names:
        try:
            counts[n] = export_entity(app_id, n, out_dir)
            print(f"{n:28s} {counts[n]:7d}")
        except Exception as e:
            counts[n] = f"FEIL: {e}"
            print(f"{n:28s} FEIL {e}")
    json.dump(counts, open(os.path.join(out_dir, 'counts.json'), 'w'), indent=2, ensure_ascii=False)

if __name__ == '__main__':
    main()

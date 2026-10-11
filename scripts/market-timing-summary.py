import json,sys,statistics
from pathlib import Path
groups={};loops=[];clocks=[]
for line in Path(sys.argv[1]).read_text(encoding='utf-8').splitlines():
    row=json.loads(line)
    if row['kind']=='clock': clocks.append(row)
    elif row['kind']=='loop': loops.append(row['health_ms'])
    elif row['kind']=='event':
        row['exchange_age_ms']=row['frame_ms']-row['exchange_ms']
        for key in ['all',row['symbol'],row['event_type'],row['symbol']+'/'+row['event_type']]:groups.setdefault(key,[]).append(row)
def stats(values):
    a=sorted(values);n=len(a)
    if not n:return {}
    return {'count':n,'mean':statistics.mean(a),'median':statistics.median(a),'p90':a[min(n-1,int(n*.9))],'p95':a[min(n-1,int(n*.95))],'p99':a[min(n-1,int(n*.99))],'max':a[-1]}
result={'groups':{key:{metric:stats([r[metric] for r in rows]) for metric in ['exchange_age_ms','parse_ms','normalize_ms','write_ms','internal_ms']} for key,rows in groups.items()},'health':stats(loops),'clock_samples':clocks}
if groups:
    rows=groups['all'];start=rows[0]['frame_ms'];buckets={}
    for r in rows:buckets.setdefault(str((r['frame_ms']-start)//60000),[]).append(r['exchange_age_ms'])
    result['age_by_minute']={k:stats(v) for k,v in buckets.items()}
Path(sys.argv[1]+'.summary.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps({k:v for k,v in result.items() if k!='groups'}|{'all':result.get('groups',{}).get('all')},indent=2))

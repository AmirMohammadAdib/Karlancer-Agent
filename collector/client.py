import json, urllib.error, urllib.request
class CollectorClient:
    def __init__(self,base_url,token,timeout=15): self.url=base_url.rstrip('/')+'/api/internal/collector/import'; self.token=token.strip(); self.timeout=timeout
    def send(self,payload):
        data=json.dumps(payload,ensure_ascii=False).encode(); req=urllib.request.Request(self.url,data=data,method='POST',headers={'Content-Type':'application/json','Authorization':'Bearer '+self.token})
        try:
            with urllib.request.urlopen(req,timeout=self.timeout) as response: return response.status,json.loads(response.read().decode())
        except urllib.error.HTTPError as e: return e.code, {'message':e.read().decode(errors='replace')[:500]}
        except urllib.error.URLError as e: return 0, {'message':str(e.reason)}

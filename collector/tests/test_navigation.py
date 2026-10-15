import unittest
from collector.main import navigate_with_retries

class TimeoutError(Exception): pass
class FakePage:
    def __init__(self, failures=0, ready=True): self.failures=failures; self.ready=ready; self.calls=0
    def goto(self,*args,**kwargs):
        self.calls+=1
        if self.calls<=self.failures: raise TimeoutError('slow')
    def wait_for_selector(self,*args,**kwargs):
        if not self.ready: raise TimeoutError('not ready')

class NavigationTests(unittest.TestCase):
    def test_timeout_then_recovery_is_allowed_when_ready(self):
        page=FakePage(failures=1); self.assertTrue(navigate_with_retries(page,'https://example.test',lambda p,t: True,retries=2)); self.assertEqual(page.calls,1)
    def test_unready_page_is_not_reported_as_success(self):
        page=FakePage(ready=False); self.assertFalse(navigate_with_retries(page,'https://example.test',lambda p,t: p.wait_for_selector('body',timeout=t) or True,retries=1))
    def test_non_timeout_errors_are_not_hidden(self):
        class Broken(FakePage):
            def goto(self,*args,**kwargs): raise ValueError('bad url')
        with self.assertRaises(ValueError): navigate_with_retries(Broken(),'x',lambda p,t: True,retries=1)

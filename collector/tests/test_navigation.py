import unittest
from collector.main import navigate_with_retries, classify_notification_page, wait_for_notification_state

class TimeoutError(Exception): pass
class FakePage:
    def __init__(self, failures=0, ready=True): self.failures=failures; self.ready=ready; self.calls=0
    def goto(self,*args,**kwargs):
        self.calls+=1
        if self.calls<=self.failures: raise TimeoutError('slow')
    def wait_for_selector(self,*args,**kwargs):
        if not self.ready: raise TimeoutError('not ready')

class NavigationTests(unittest.TestCase):
    def test_populated_page_uses_parser_supported_project_links(self):
        state=classify_notification_page('https://www.karlancer.com/panel/notifications',"<body><a href='/projects/x'>پروژه</a></body>")
        self.assertEqual(state['classification'],'populated'); self.assertEqual(state['candidate_count'],1)
    def test_valid_empty_page_is_distinguished(self):
        state=classify_notification_page('https://www.karlancer.com/panel/notifications','<body><p>اعلان جدیدی نیست</p></body>')
        self.assertEqual(state['classification'],'empty'); self.assertEqual(state['candidate_count'],0)
    def test_login_page_is_distinguished(self):
        state=classify_notification_page('https://www.karlancer.com/login','<body><input type="password"></body>')
        self.assertEqual(state['classification'],'login')
    def test_delayed_page_becomes_populated(self):
        class Delayed:
            url='https://www.karlancer.com/panel/notifications'
            def __init__(self): self.i=0
            def content(self): self.i+=1; return '<body></body>' if self.i<2 else "<body><a href='/projects/x'>x</a></body>"
            def wait_for_timeout(self,_): pass
        self.assertEqual(wait_for_notification_state(Delayed(),1000)['classification'],'populated')
    def test_timeout_then_recovery_is_allowed_when_ready(self):
        page=FakePage(failures=1); self.assertTrue(navigate_with_retries(page,'https://example.test',lambda p,t: True,retries=2)); self.assertEqual(page.calls,1)
    def test_unready_page_is_not_reported_as_success(self):
        page=FakePage(ready=False); self.assertFalse(navigate_with_retries(page,'https://example.test',lambda p,t: p.wait_for_selector('body',timeout=t) or True,retries=1))
    def test_non_timeout_errors_are_not_hidden(self):
        class Broken(FakePage):
            def goto(self,*args,**kwargs): raise ValueError('bad url')
        with self.assertRaises(ValueError): navigate_with_retries(Broken(),'x',lambda p,t: True,retries=1)

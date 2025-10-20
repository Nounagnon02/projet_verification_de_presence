from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()
    # The application is not running, so I'll open the HTML file directly.
    page.goto('file:///app/resources/views/auth/login.blade.php')
    page.screenshot(path='jules-scratch/verification/verification.png')
    browser.close()

with sync_playwright() as playwright:
    run(playwright)

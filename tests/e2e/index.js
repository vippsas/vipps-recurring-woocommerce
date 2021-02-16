import { By } from 'selenium-webdriver'
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver'

const manager = new WebDriverManager('chrome')
const driver = manager.getDriver()

driver.get('https://wp-e2e-test-form-page.herokuapp.com/index.html')
helper.setWhenSettable(driver, By.css('input[name="email"]'), 'john.doe@example.com')
helper.setCheckbox(driver, By.css('#exampleCheckbox'))

setTimeout(() => {
  manager.quitBrowser()
}, 5000)

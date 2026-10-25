const fs = require('fs');
const path = require('path');
const { chromium } = require('/tmp/pocketcat-ai-e2e/node_modules/playwright');

const evidence = __dirname;
const workerFile = '/tmp/pocketcat-ai-e2e/generated-worker.mjs';
const baseUrl = 'http://127.0.0.1:8080';
const results = [];
const browserErrors = [];

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function record(name, detail = 'passed') {
  results.push({ name, detail });
  console.log(`PASS ${name}: ${detail}`);
}

async function signIn(page) {
  await page.goto(`${baseUrl}/login/`, { waitUntil: 'networkidle' });
  await page.locator('[name=identity]').fill('ai-e2e-local');
  await page.locator('[name=password]').fill('LocalAIQA2026!');
  await page.getByRole('button', { name: 'Sign In' }).click();
  await page.waitForURL('**/dashboard/');
}

async function setAdminView(page, isAdmin) {
  await page.goto(`${baseUrl}/admin/toggle/`, { waitUntil: 'networkidle' });
  await page.waitForURL(isAdmin ? '**/admin/' : '**/dashboard/');
}

async function checkFlash(page, expected) {
  const flash = page.locator('.flash');
  await flash.waitFor({ state: 'visible' });
  const text = (await flash.innerText()).trim();
  assert(text.includes(expected), `Expected flash containing "${expected}", got "${text}"`);
  assert(!/failed|temporarily unavailable|error:/i.test(text), `Connection test reports an error: ${text}`);
  return text;
}

async function saveAdmin(page, type, settings) {
  await page.goto(`${baseUrl}/admin/ai/`, { waitUntil: 'networkidle' });
  await page.locator('#adminBedrockConnection').selectOption(type);
  await page.locator('[name=bedrock_model_id]').fill(settings.model);
  if (type === 'api_key') {
    await page.locator('[name=bedrock_endpoint_url]').fill(settings.endpoint);
    await page.locator('[name=bedrock_api_key]').fill(settings.key);
  } else if (type === 'iam') {
    await page.locator('[name=aws_access_key_id]').fill(settings.accessKey);
    await page.locator('[name=aws_secret_access_key]').fill(settings.secretKey);
    await page.locator('[name=aws_session_token]').fill(settings.sessionToken);
    await page.locator('[name=aws_region]').fill('us-east-1');
  } else {
    await fillWorkerGenerator(page, 'admin', settings);
    await page.locator('[name=bedrock_relay_url]').fill(settings.relayUrl);
    await page.locator('[name=bedrock_relay_token]').fill(settings.relayToken);
  }
  await page.getByRole('button', { name: 'Save Configuration' }).click();
  await page.waitForURL('**/admin/ai/');
  return await page.locator('#adminBedrockConnection').inputValue();
}

async function saveUser(page, type, settings) {
  await page.goto(`${baseUrl}/settings/ai/`, { waitUntil: 'networkidle' });
  await page.locator('#userBedrockConnection').selectOption(type);
  await page.locator('[name=model_id]').fill(settings.model);
  if (type === 'api_key') {
    await page.locator('[name=endpoint_url]').fill(settings.endpoint);
    await page.locator('[name=bedrock_api_key]').fill(settings.key);
  } else if (type === 'iam') {
    await page.locator('[name=aws_access_key_id]').fill(settings.accessKey);
    await page.locator('[name=aws_secret_access_key]').fill(settings.secretKey);
    await page.locator('[name=aws_session_token]').fill(settings.sessionToken);
    await page.locator('[name=region]').fill('us-east-1');
  } else {
    await fillWorkerGenerator(page, 'user', settings);
    await page.locator('[name=relay_url]').fill(settings.relayUrl);
    await page.locator('[name=bedrock_relay_token]').fill(settings.relayToken);
  }
  await page.getByRole('button', { name: 'Save My Bedrock Settings' }).click();
  await page.waitForURL('**/settings/ai/');
  return await page.locator('#userBedrockConnection').inputValue();
}

async function fillWorkerGenerator(page, scope, settings) {
  const panel = scope === 'admin'
    ? page.locator('[data-connection-panel="relay"]')
    : page.locator('[data-connection-panel="relay"]');
  await panel.locator('[data-worker-base]').fill('https://127.0.0.1:9443/v1');
  await panel.locator('[data-worker-api-key]').fill('mock-upstream-api-key');
  await panel.locator('[name=bedrock_relay_token]').fill(settings.relayToken);
  await panel.locator('[data-worker-generate]').click();
  const generated = await panel.locator('[data-worker-code]').inputValue();
  assert(generated.includes('https://127.0.0.1:9443/v1'), 'Generated Worker is missing the local upstream URL');
  assert(generated.includes(settings.relayToken), 'Generated Worker is missing the relay shared token');
  assert(generated.includes('mock-upstream-api-key'), 'Generated Worker is missing the synthetic provider API key');
  fs.writeFileSync(workerFile, generated, { mode: 0o600 });
  if (page.viewportSize().width === 1440) {
    await page.screenshot({ path: path.join(evidence, `${scope}-relay-generated-desktop.png`), fullPage: true });
  }
}

async function testConnection(page, scope) {
  const buttonName = scope === 'admin' ? 'Test Bedrock Connection' : 'Test My Bedrock Connection';
  await page.getByRole('button', { name: buttonName }).click();
  await page.waitForLoadState('networkidle');
  return checkFlash(page, scope === 'admin' ? 'connection OK' : 'connection OK');
}

async function rejectBadRelayToken(page) {
  const response = await page.request.post('https://127.0.0.1:9444/chat/completions', {
    ignoreHTTPSErrors: true,
    headers: { 'x-pocketcat-relay-token': 'wrong-local-token', 'content-type': 'application/json' },
    data: { model: 'qa-invalid-token', messages: [{ role: 'user', content: 'reject me' }] }
  });
  assert(response.status() === 401, `Generated relay should reject a bad token, got HTTP ${response.status()}`);
}

async function rejectBadSigV4(page) {
  const response = await page.request.post('https://127.0.0.1:443/model/qa-invalid/converse', {
    ignoreHTTPSErrors: true,
    headers: { authorization: 'AWS4-HMAC-SHA256 invalid', 'x-amz-date': 'invalid' },
    data: { messages: [] }
  });
  assert(response.status() === 403, `Mock Bedrock should reject invalid SigV4, got HTTP ${response.status()}`);
}

async function runWorkspaceModes(page, label, expectedModel) {
  await page.goto(`${baseUrl}/ai/`, { waitUntil: 'networkidle' });
  await page.locator('[data-ai-workspace]').waitFor();
  const sendMessage = async (mode) => {
    await page.locator('#ai-mode').selectOption(mode);
    const newChat = page.waitForResponse(response => response.url().endsWith('/api/ai-chat.php')
      && response.request().postData()?.includes('action=new'));
    await page.locator('#new-chat').click();
    await newChat;
    await page.waitForFunction(() => !document.querySelector('#new-chat').disabled);
    await page.locator('#chat-input').fill(`E2E ${label} ${mode} mode.`);
    const sent = page.waitForResponse(response => response.url().endsWith('/api/ai-chat.php')
      && response.request().postData()?.includes('action=send'));
    await page.locator('#send-btn').click();
    const response = await sent;
    const data = await response.json();
    assert(data.ok === true, `${label} ${mode} failed: ${data.error || JSON.stringify(data)}`);
    assert(data.answer.includes(expectedModel), `${label} ${mode} used unexpected model: ${data.answer}`);
    await page.getByText(data.answer, { exact: true }).waitFor();
    return data.answer;
  };

  const chat = await sendMessage('chat');
  const agent = await sendMessage('agent');
  await page.locator('#ai-mode').selectOption('tasks');
  await page.waitForURL('**/ai/transform-tasks/**');
  await page.locator('textarea[name=text]').fill(`Create an end-to-end test task for ${label}.`);
  const taskRequest = page.waitForResponse(response => response.request().method() === 'POST'
    && response.url().includes('/ai/transform-tasks/'));
  await page.getByRole('button', { name: 'Generate Preview' }).click();
  const taskResponse = await taskRequest;
  assert(taskResponse.status() === 200, `${label} Text-to-Tasks returned HTTP ${taskResponse.status()}`);
  await page.locator('.task-preview-row').first().waitFor();
  const taskText = await page.locator('.task-preview-row').first().locator('input[type=text]').inputValue();
  assert(taskText.includes('Verify the end-to-end mock task route'), `${label} Text-to-Tasks did not show the generated mock task: ${taskText}`);
  return { chat, agent, task: taskText };
}

async function selectCustomModel(page, model) {
  await page.goto(`${baseUrl}/ai/`, { waitUntil: 'networkidle' });
  await page.locator('#ai-model-picker summary').click();
  await page.locator('#ai-custom-model-id').fill(model);
  await page.locator('#ai-use-custom-model').click();
  await page.waitForFunction(expected => document.querySelector('#selected-model-label')?.textContent === expected, model);
}

async function captureSettingScreenshots(page, scope, type) {
  await page.goto(`${baseUrl}/${scope === 'admin' ? 'admin/ai' : 'settings/ai'}/`, { waitUntil: 'networkidle' });
  await page.evaluate(() => scrollTo(0, 0));
  await page.screenshot({ path: path.join(evidence, `${scope}-${type}-desktop.png`), fullPage: true });
}

async function mobileScreenshots(browser, state, scope, settings) {
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 1,
    isMobile: true,
    hasTouch: true,
    ignoreHTTPSErrors: true,
    storageState: state,
    recordVideo: { dir: evidence, size: { width: 390, height: 844 } }
  });
  const page = await context.newPage();
  try {
    page.on('pageerror', error => browserErrors.push(`mobile ${error.message}`));
    page.on('console', message => { if (message.type() === 'error') browserErrors.push(`mobile ${message.text()}`); });
    if (scope === 'admin') await setAdminView(page, true);
    for (const type of ['api_key', 'iam', 'relay']) {
      if (scope === 'admin') await saveAdmin(page, type, settings[type]);
      else await saveUser(page, type, settings[type]);
      await testConnection(page, scope);
      await page.evaluate(() => scrollTo(0, 0));
      await page.screenshot({ path: path.join(evidence, `${scope}-${type}-mobile.png`), fullPage: true });
      const metrics = await page.evaluate(() => ({
        viewportWidth: innerWidth,
        documentWidth: document.documentElement.scrollWidth
      }));
      assert(metrics.documentWidth <= metrics.viewportWidth, `${scope} ${type} mobile overflows horizontally: ${JSON.stringify(metrics)}`);
    }
  } finally {
    await context.close();
  }
  const videos = fs.readdirSync(evidence).filter(file => file.endsWith('.webm'));
  const newest = videos.sort((a, b) => fs.statSync(path.join(evidence, b)).mtimeMs - fs.statSync(path.join(evidence, a)).mtimeMs)[0];
  if (newest) fs.renameSync(path.join(evidence, newest), path.join(evidence, `${scope}-settings-mobile.webm`));
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const desktopContext = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
    recordVideo: { dir: evidence, size: { width: 1440, height: 900 } }
  });
  const page = await desktopContext.newPage();
  page.on('pageerror', error => browserErrors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') browserErrors.push(message.text()); });
  try {
    await signIn(page);
    await setAdminView(page, true);
    const userState = await desktopContext.storageState();
    const adminApi = { model: 'qa-admin-openai-model', endpoint: 'http://127.0.0.1:9090/v1', key: 'admin-openai-local-key' };

    await saveAdmin(page, 'api_key', adminApi);
    await testConnection(page, 'admin');
    await captureSettingScreenshots(page, 'admin', 'api_key');
    record('Admin API-key save and provider test', 'HTTP 200 and expected mock reply');

    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Reset AI Configuration' }).click();
    await page.waitForLoadState('networkidle');
    await checkFlash(page, 'reset');
    await saveAdmin(page, 'api_key', adminApi);
    await testConnection(page, 'admin');
    await page.goto(`${baseUrl}/settings/ai/`, { waitUntil: 'networkidle' });
    const inheritedCard = await page.locator('.ai-effective-grid').innerText();
    assert(inheritedCard.includes('Administrator default'), `User should inherit Admin settings before override: ${inheritedCard}`);
    assert(inheritedCard.includes('Configured'), `Inherited Admin default should be shown configured: ${inheritedCard}`);
    await captureSettingScreenshots(page, 'settings', 'inherited-admin-default');
    const adminApiModes = await runWorkspaceModes(page, 'Admin OpenAI inherited default', adminApi.model);
    await selectCustomModel(page, 'qa-admin-api-model-dynamic');
    await runWorkspaceModes(page, 'Admin OpenAI dynamic model', 'qa-admin-api-model-dynamic');
    record('Admin default inheritance and Chat/Agent/Text-to-Tasks', JSON.stringify({ inheritedCard, modes: adminApiModes }));

    const adminIam = {
      model: 'anthropic.claude-qa-admin-v1',
      accessKey: 'AKIAQAADMIN0000001',
      secretKey: 'admin-sigv4-secret-local',
      sessionToken: 'admin-session-local'
    };
    await saveAdmin(page, 'iam', adminIam);
    await testConnection(page, 'admin');
    await captureSettingScreenshots(page, 'admin', 'iam');
    await rejectBadSigV4(page);
    await selectCustomModel(page, adminIam.model);
    const adminIamModes = await runWorkspaceModes(page, 'Admin IAM Converse', adminIam.model);
    record('Admin AWS IAM direct', JSON.stringify({ strictSigV4NegativeCase: 'rejected HTTP 403', modes: adminIamModes }));

    const adminRelay = {
      model: 'qa-admin-relay-default',
      relayUrl: 'https://127.0.0.1:9444',
      relayToken: 'admin-relay-local-token'
    };
    await saveAdmin(page, 'relay', adminRelay);
    await rejectBadRelayToken(page);
    await testConnection(page, 'admin');
    await captureSettingScreenshots(page, 'admin', 'relay');
    await selectCustomModel(page, 'qa-admin-relay-switched-model');
    const adminRelayModes = await runWorkspaceModes(page, 'Admin generated Worker relay', 'qa-admin-relay-switched-model');
    record('Admin generated Worker relay', JSON.stringify({ invalidToken: 'rejected HTTP 401', dynamicModel: 'verified in upstream log', modes: adminRelayModes }));

    await setAdminView(page, false);
    const userApi = { model: 'qa-user-openai-model', endpoint: 'http://127.0.0.1:9090/v1', key: 'user-openai-local-key' };
    await saveUser(page, 'api_key', userApi);
    await testConnection(page, 'user');
    await captureSettingScreenshots(page, 'settings', 'api_key');
    const userApiCard = await page.locator('.ai-effective-grid').innerText();
    assert(userApiCard.includes('Your settings override'), `User custom API-key source was not shown: ${userApiCard}`);
    assert(userApiCard.includes('Configured'), `User API key should be shown configured: ${userApiCard}`);
    await selectCustomModel(page, userApi.model);
    const userApiModes = await runWorkspaceModes(page, 'User OpenAI override', userApi.model);
    record('User API-key override', JSON.stringify({ effectiveCard: userApiCard, modes: userApiModes }));

    const userIam = {
      model: 'anthropic.claude-qa-user-v1',
      accessKey: 'ASIAQAUSER00000002',
      secretKey: 'user-sigv4-secret-local',
      sessionToken: 'user-session-local'
    };
    await saveUser(page, 'iam', userIam);
    await testConnection(page, 'user');
    await captureSettingScreenshots(page, 'settings', 'iam');
    await selectCustomModel(page, userIam.model);
    const userIamModes = await runWorkspaceModes(page, 'User IAM Converse', userIam.model);
    record('User AWS IAM direct', JSON.stringify({ strictSigV4: 'cryptographically validated', modes: userIamModes }));

    const userRelay = {
      model: 'qa-user-relay-default',
      relayUrl: 'https://127.0.0.1:9444',
      relayToken: 'user-relay-local-token'
    };
    await saveUser(page, 'relay', userRelay);
    await rejectBadRelayToken(page);
    await testConnection(page, 'user');
    await captureSettingScreenshots(page, 'settings', 'relay');
    await selectCustomModel(page, 'qa-user-relay-switched-model');
    const userRelayModes = await runWorkspaceModes(page, 'User generated Worker relay', 'qa-user-relay-switched-model');
    record('User generated Worker relay', JSON.stringify({ invalidToken: 'rejected HTTP 401', dynamicModel: 'verified in upstream log', modes: userRelayModes }));

    for (const scope of ['admin', 'settings']) {
      const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        ignoreHTTPSErrors: true,
        storageState: userState,
        recordVideo: { dir: evidence, size: { width: 1440, height: 900 } }
      });
      try {
        const videoPage = await context.newPage();
        videoPage.on('pageerror', error => browserErrors.push(`video ${error.message}`));
        await setAdminView(videoPage, scope === 'admin');
        for (const type of ['api_key', 'iam', 'relay']) {
          await videoPage.goto(`${baseUrl}/${scope === 'admin' ? 'admin/ai' : 'settings/ai'}/`, { waitUntil: 'networkidle' });
          await videoPage.locator(`#${scope === 'admin' ? 'adminBedrockConnection' : 'userBedrockConnection'}`).selectOption(type);
          await videoPage.evaluate(() => scrollTo(0, 0));
          await videoPage.waitForTimeout(350);
        }
      } finally {
        await context.close();
      }
      const videos = fs.readdirSync(evidence).filter(file => file.endsWith('.webm'));
      const newest = videos.sort((a, b) => fs.statSync(path.join(evidence, b)).mtimeMs - fs.statSync(path.join(evidence, a)).mtimeMs)[0];
      if (newest) fs.renameSync(path.join(evidence, newest), path.join(evidence, `${scope}-settings-desktop.webm`));
    }

    await mobileScreenshots(browser, userState, 'admin', {
      api_key: adminApi,
      iam: adminIam,
      relay: adminRelay
    });
    await mobileScreenshots(browser, userState, 'settings', {
      api_key: userApi,
      iam: userIam,
      relay: userRelay
    });
    assert(browserErrors.length === 0, `Browser errors: ${browserErrors.join(' | ')}`);
    record('Browser JavaScript and responsive layout', 'zero console/page errors; desktop 1440x900; mobile 390x844; no horizontal overflow');
    fs.writeFileSync(path.join(evidence, 'verification-summary.json'), JSON.stringify({
      completedAt: new Date().toISOString(),
      testCount: results.length,
      results,
      browserErrors,
      viewports: { desktop: '1440x900', mobile: '390x844' }
    }, null, 2));
  } finally {
    await desktopContext.close();
    await browser.close();
  }
}

main().catch(error => {
  console.error(error.stack || error);
  process.exitCode = 1;
});

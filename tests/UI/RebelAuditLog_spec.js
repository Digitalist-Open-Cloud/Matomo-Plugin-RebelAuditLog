describe('RebelAuditLog', function () {
    this.fixture = 'Piwik\\Plugins\\RebelAuditLog\\tests\\Fixtures\\SiteWithAuditLog';

    const pageUrl = '?module=RebelAuditLog&action=index&idSite=1&period=day&date=2024-03-05';

    before(function () {
        testEnvironment.pluginsToLoad = ['RebelAuditLog'];
        testEnvironment.save();
    });

    function messages() {
        return page.evaluate(() => [...document.querySelectorAll('.rebelAuditLog__message')]
            .map((cell) => cell.textContent.trim()));
    }

    it('should show the newest entries of the day first', async function () {
        await page.goto(pageUrl);
        await page.waitForSelector('.rebelAuditLog__table');

        const shown = await messages();
        expect(shown).to.have.lengthOf(50);
        expect(shown[0]).to.equal('carl was added as super user.');
        expect(await page.evaluate(() => document.querySelector('.rebelAuditLog__pages').textContent)).to.contain('Entries: 61');
    });

    it('should show the rest on the next page', async function () {
        await page.click('.rebelAuditLog__next');
        await page.waitForSelector('.rebelAuditLog__previous');

        expect(await messages()).to.have.lengthOf(11);
    });

    it('should show only the entries of the chosen user', async function () {
        await page.goto(pageUrl);
        await page.select('.rebelAuditLog select[name=user]', 'bo');
        await Promise.all([page.waitForNavigation(), page.click('.rebelAuditLog__filters .btn')]);
        await page.waitForSelector('.rebelAuditLog__table');

        expect(await messages()).to.deep.equal(['carl was added as super user.']);
    });

    it('should tell when nothing was logged in the period', async function () {
        await page.goto(pageUrl.replace('2024-03-05', '2024-03-06'));
        await page.waitForSelector('.rebelAuditLog__table');

        const text = await page.evaluate(() => document.querySelector('.rebelAuditLog__table tbody').textContent);
        expect(text).to.contain('Nothing was logged in this period.');
    });
});

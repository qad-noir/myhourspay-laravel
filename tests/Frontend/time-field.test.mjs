import test from 'node:test';
import assert from 'node:assert/strict';
globalThis.window = {matchMedia: () => ({matches:false, addEventListener(){}, removeEventListener(){}})};
const { timeField } = await import('../../resources/js/time-field.js');
await import('../../resources/js/record-drawer.js');

test('explicit periods produce unambiguous server times, including noon and midnight', () => {
    const field = timeField();
    for (const [clock, period, expected] of [
        ['06:15', 'AM', '06:15'], ['04:15', 'PM', '16:15'],
        ['12:00', 'AM', '00:00'], ['12:00', 'PM', '12:00'], ['1:09', 'PM', '13:09'],
    ]) {
        Object.assign(field, {clock, period});
        field.commit();
        assert.equal(field.value, expected);
    }
});

test('incomplete and invalid times cannot retain a stale valid submission value', () => {
    const field = timeField();
    for (const clock of ['', '4:', '13:00', '00:00', '04:60', 'banana']) {
        Object.assign(field, {clock, period:'PM', value:'16:15'});
        field.commit();
        assert.equal(field.value, '');
    }
});

test('loaded records restore the correct period without erasing in-progress invalid text', () => {
    const field = timeField();
    let sync;
    field.$watch = (_, callback) => { sync = callback; };
    field.init();
    sync('17:30');
    assert.equal(field.clock, '05:30');
    assert.equal(field.period, 'PM');
    sync('00:45');
    assert.equal(field.clock, '12:45');
    assert.equal(field.period, 'AM');
    field.clock = '13:00';
    field.commit();
    sync(field.value);
    assert.equal(field.clock, '13:00');
});

test('schedule drawers initialise the shared footer loading state and block lookup-time saves', async () => {
    const drawer = window.scheduleEditor([], '/pro/schedules');
    assert.equal(drawer.checkingEntry, false);
    drawer.checkingEntry = true;
    let sent = false;
    drawer.send = () => { sent = true; };
    await drawer.save({reportValidity:()=>true});
    assert.equal(sent, false);
});

test('native picker changes sync to the desktop editor and switching modes retains values', () => {
    const media = {matches:true,addEventListener(_,fn){this.listener=fn;},removeEventListener(_,fn){assert.equal(fn,this.listener);}};
    window.matchMedia = () => media;
    const field = timeField();
    let sync;
    field.$watch = (_,fn) => {sync=fn;};
    field.init();
    assert.equal(field.native,true);
    field.setNative('16:15');sync(field.value);
    assert.equal(field.clock,'04:15');assert.equal(field.period,'PM');
    media.matches=false;media.listener();
    assert.equal(field.native,false);assert.equal(field.value,'16:15');
    field.setNative('');sync(field.value);
    assert.equal(field.clock,'');assert.equal(field.value,'');
    field.destroy();
});

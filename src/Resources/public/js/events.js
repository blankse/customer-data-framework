/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

pimcore.events.cmfMenuReady = 'pimcore.cmf.menu.ready';

if(typeof addEventListenerCompatibilityForPlugins === "function") {
    let eventMappings = [];
    eventMappings["cmfMenuReady"] = pimcore.events.cmfMenuReady;
    addEventListenerCompatibilityForPlugins(eventMappings);
} else {
    console.error("Delete addEventListenerCompatibilityForPlugins in the customer-management-framework-bundle");
}

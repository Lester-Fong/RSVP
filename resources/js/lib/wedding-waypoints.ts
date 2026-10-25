export interface WeddingWaypoint {
    id: string;
    kind: 'section' | 'milestone';
    section: string;
    element: Element;
}

/** Fresh, document-ordered destinations, including offscreen markers. Geometry belongs to the future consumer. */
export function discoverWeddingWaypoints(root: ParentNode): WeddingWaypoint[] {
    return [...root.querySelectorAll<SVGElement>('[data-landing-id][data-landing-section]')]
        .filter((element) => {
            const view = element.ownerDocument.defaultView;
            return (
                element.isConnected &&
                !element.closest('[hidden], [inert]') &&
                element.getClientRects().length > 0 &&
                view?.getComputedStyle(element).visibility !== 'hidden'
            );
        })
        .map((element) => ({
            id: element.dataset.landingId!,
            kind: element.dataset.landingMilestone ? 'milestone' : 'section',
            section: element.dataset.landingSection!,
            element,
        }));
}

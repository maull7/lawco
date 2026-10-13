export default function sectorSelector(sectors, initialSelected = []) {
    const options = sectors.map(sector => ({ ...sector, id: String(sector.id) }));
    const validIds = new Set(options.map(sector => sector.id));
    const namesById = new Map(options.map(sector => [sector.id, sector.name.toLocaleLowerCase('id')]));

    return {
        query: '',
        selected: [...new Set(initialSelected.map(String))].filter(id => validIds.has(id)),
        get filteredOptions() {
            const term = this.query.trim().toLocaleLowerCase('id');
            return options.filter(sector => sector.name.toLocaleLowerCase('id').includes(term));
        },
        get selectedOptions() {
            return options.filter(sector => this.selected.includes(sector.id));
        },
        isVisible(id) {
            return namesById.get(String(id))?.includes(this.query.trim().toLocaleLowerCase('id')) ?? false;
        },
        selectVisible() {
            this.selected = [...new Set([...this.selected, ...this.filteredOptions.map(sector => sector.id)])];
        },
        clear() {
            this.selected = [];
        },
    };
}

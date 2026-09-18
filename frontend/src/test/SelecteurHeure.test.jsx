import { render, screen, fireEvent } from '@testing-library/react';
import SelecteurHeure from '../components/ui/SelecteurHeure';
import { enHeure, enMinutes, finApresNouveauDebut } from '../utils/heures';

describe('utils/heures', () => {
  it('convertit dans les deux sens, et ignore les secondes', () => {
    expect(enMinutes('08:15')).toBe(495);
    expect(enMinutes('08:15:40')).toBe(495);
    expect(enMinutes('')).toBeNull();
    expect(enMinutes(null)).toBeNull();
    expect(enHeure(495)).toBe('08:15');
  });

  it('conserve la durée quand le début change', () => {
    expect(finApresNouveauDebut({
      ancienDebut: '08:00', ancienneFin: '10:00', nouveauDebut: '14:00',
    })).toBe('16:00');
  });

  it('borne la fin à la limite de durée', () => {
    // Une séance de 4 h déplacée, avec seulement 2 h permises.
    expect(finApresNouveauDebut({
      ancienDebut: '08:00', ancienneFin: '12:00', nouveauDebut: '14:00', limiteMinutes: 120,
    })).toBe('16:00');
  });

  it('propose 2 h par défaut quand aucune durée n\'est encore choisie', () => {
    expect(finApresNouveauDebut({ ancienDebut: '', ancienneFin: '', nouveauDebut: '09:00' })).toBe('11:00');
  });

  it('ne dépasse jamais la fin de journée', () => {
    expect(finApresNouveauDebut({
      ancienDebut: '08:00', ancienneFin: '12:00', nouveauDebut: '21:00',
    })).toBe('22:00');
  });
});

describe('SelecteurHeure', () => {
  const heuresProposees = () => screen.getAllByRole('option')
    .filter((o) => o.value !== '')
    .map((o) => o.textContent);

  it('affiche la valeur, même hors de la grille de 15 minutes', () => {
    render(<SelecteurHeure value="06:26" onChange={() => {}} />);
    expect(screen.getByDisplayValue('06:26')).toBeInTheDocument();
  });

  it('ne propose que les heures comprises entre les bornes', () => {
    render(<SelecteurHeure value="" onChange={() => {}} apres="08:00" jusqua="10:00" placeholder="Fin" />);

    // Borne basse exclue (une fin ne peut pas égaler le début), haute incluse.
    expect(heuresProposees()).toEqual(['08:15', '08:30', '08:45', '09:00', '09:15', '09:30', '09:45', '10:00']);
  });

  it('propose la journée entière, de 06:00 à 22:00, sans borne', () => {
    render(<SelecteurHeure value="" onChange={() => {}} />);

    const heures = heuresProposees();
    expect(heures[0]).toBe('06:00');
    expect(heures.at(-1)).toBe('22:00');
    expect(heures).toHaveLength(65);
  });

  it('renvoie une heure « HH:MM » au choix', () => {
    const onChange = vi.fn();
    render(<SelecteurHeure value="" onChange={onChange} apres="08:00" jusqua="09:00" placeholder="Fin" />);

    fireEvent.change(screen.getByRole('combobox'), { target: { value: '08:45' } });

    expect(onChange).toHaveBeenCalledWith('08:45');
  });

  it('renvoie une chaîne vide quand on revient au choix vide', () => {
    const onChange = vi.fn();
    render(<SelecteurHeure value="08:00" onChange={onChange} />);

    fireEvent.change(screen.getByRole('combobox'), { target: { value: '' } });

    expect(onChange).toHaveBeenCalledWith('');
  });

  it('garde sélectionnable une heure enregistrée hors des bornes, à sa place dans la liste', () => {
    // Une fin de 07:10 pour un début de 08:00 (donnée importée) : la
    // supprimer à l'ouverture modifierait la séance sans que personne l'ait voulu.
    render(<SelecteurHeure value="07:10" onChange={() => {}} apres="08:00" jusqua="09:00" />);

    expect(screen.getByRole('combobox')).toHaveValue('07:10');
    expect(heuresProposees()).toEqual(['07:10', '08:15', '08:30', '08:45', '09:00']);
  });

  it('se nomme par défaut, sauf quand un id la relie au libellé du parent', () => {
    const { rerender } = render(<SelecteurHeure value="" onChange={() => {}} />);
    expect(screen.getByRole('combobox', { name: 'Choisir une heure' })).toBeInTheDocument();

    rerender(
      <>
        <label htmlFor="debut">Début</label>
        <SelecteurHeure id="debut" value="" onChange={() => {}} />
      </>,
    );
    // Le libellé visible du parent nomme le champ : aucun aria-label ne le masque.
    expect(screen.getByRole('combobox', { name: 'Début' })).not.toHaveAttribute('aria-label');
  });
});

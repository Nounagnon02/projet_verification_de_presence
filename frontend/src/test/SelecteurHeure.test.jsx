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
  const heuresVisibles = () => [...document.querySelectorAll('.react-datepicker__time-list-item')]
    .filter((el) => !el.classList.contains('react-datepicker__time-list-item--disabled'))
    .map((el) => el.textContent);

  it('affiche la valeur, même hors de la grille de 15 minutes', () => {
    render(<SelecteurHeure value="06:26" onChange={() => {}} />);
    expect(screen.getByDisplayValue('06:26')).toBeInTheDocument();
  });

  it('ne propose que les heures comprises entre les bornes', () => {
    render(<SelecteurHeure value="" onChange={() => {}} apres="08:00" jusqua="10:00" placeholder="Fin" />);
    fireEvent.click(screen.getByPlaceholderText('Fin'));

    // Borne basse exclue (une fin ne peut pas égaler le début), haute incluse.
    expect(heuresVisibles()).toEqual(['08:15', '08:30', '08:45', '09:00', '09:15', '09:30', '09:45', '10:00']);
  });

  it('renvoie une heure « HH:MM » au choix', () => {
    const onChange = vi.fn();
    render(<SelecteurHeure value="" onChange={onChange} apres="08:00" jusqua="09:00" placeholder="Fin" />);
    fireEvent.click(screen.getByPlaceholderText('Fin'));
    fireEvent.click(screen.getByText('08:45'));

    expect(onChange).toHaveBeenCalledWith('08:45');
  });

  it('empêche la saisie au clavier', () => {
    render(<SelecteurHeure value="08:00" onChange={() => {}} />);
    const champ = screen.getByDisplayValue('08:00');
    const evenement = new KeyboardEvent('keydown', { key: '9', bubbles: true, cancelable: true });
    champ.dispatchEvent(evenement);

    expect(evenement.defaultPrevented).toBe(true);
    expect(champ).toHaveAttribute('inputmode', 'none');
  });
});

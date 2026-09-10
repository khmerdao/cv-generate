import { render, screen } from '@testing-library/react';
import HelloIsland from './controllers/HelloIsland';

test('renders the greeting with the given name', () => {
    render(<HelloIsland name="jane@example.com" greeting="Hello" />);
    expect(screen.getByRole('status')).toHaveTextContent('Hello, jane@example.com');
});


import logo from './logo.png';

import 'bootstrap/dist/css/bootstrap.min.css';
import './App.css';

import { Container, Row, Col, Nav, Navbar, NavDropdown, Card, Button, ProgressBar } from 'react-bootstrap';

function App() {
  return (
    <Container fluid>

      <Navbar collapseOnSelect expand="lg" bg="warning" variant="light">
        <Container>
        <Navbar.Brand href="#home"><img src={logo} className="img-responsive logo" alt="logo" /></Navbar.Brand>
        <Navbar.Toggle aria-controls="responsive-navbar-nav" />
        <Navbar.Collapse id="responsive-navbar-nav">
          <Nav className="mr-auto">

          </Nav>
          <Nav>
            <Nav.Link href="#settings">Settings</Nav.Link>
          </Nav>
        </Navbar.Collapse>
        </Container>
      </Navbar>

      <Container className="main-content">

        <Card className="text bg-warning">
          <Card.Header>Featured</Card.Header>
          <Card.Body>
            <Card.Text>
              With supporting text below as a natural lead-in to additional content.
            </Card.Text>
            <ProgressBar className="bg-dark" variant="danger" now={60} label={`${60}%`} />
          </Card.Body>
        </Card>


      </Container>


    </Container>
  );
}

export default App;
